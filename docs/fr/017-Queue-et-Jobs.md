# 17 La Queue et les Jobs

## Les jobs et comment on les lance

Un job, c'est une tâche qu'on met de côté pour la faire plus tard, en arrière-plan, sans faire attendre la requête en cours. C'est une classe qui étend `MasterJob` :

```php
class SendMailJob extends MasterJob {
    public string|array $to;
    public MasterMail $mail;

    public function __construct(string|array $to, MasterMail $mail) {
        $this->to   = $to;
        $this->mail = $mail;
    }

    public function run(): void {
        MailSender::_send($this->to, $this->mail);
    }
}
```

Pour le mettre en attente, tu fais `Queue::push()` :

```php
Queue::push(new SendMailJob($email, new WelcomeMail($name)));
```

`push()` range le job dans un `JobDoc` (statut `pending`, `attempts` à 0, `max_attempts` à 3 par défaut) et le sauve dans la collection `jobs`. C'est tout : la requête en cours continue sans attendre que le job tourne.

## La commande pour les workers

Le worker, c'est lui qui exécute les jobs. Tu le lances avec :

```bash
php craft queue-worker
```

C'est une boucle infinie : il réserve le job `pending` le plus ancien (passé en `processing`, `attempts` + 1), exécute `run()`, puis :

- **succès** : le `JobDoc` est supprimé. Pas besoin de le garder en base, et ça évite qu'un contenu potentiellement sensible y traîne indéfiniment.
- **échec** (tout ce qui est lancé, `\Error` compris) : le statut repasse à `pending` pour un nouvel essai, sauf si `max_attempts` est atteint : le statut devient alors `failed`. L'erreur est loggée (`Log::error()`, voir [15 Les Logs](015-Les-Logs.md)) et son message est gardé sur le `JobDoc`.

Sans job en attente, la boucle attend une seconde avant de reboucler (`sleep(1)`).

Le statut est un enum `JobStatus` (`PENDING`, `PROCESSING`, `FAILED`). Dans une requête Mongo brute, on passe sa valeur : `['status' => JobStatus::FAILED->value]`.

### Plusieurs workers

Tu peux lancer autant de workers que tu veux : réserver un job est une seule opération atomique (`findOneAndUpdate`), donc deux workers ne récupèrent jamais le même job.

### Le bail : quand un worker meurt

Si un worker meurt en plein job (crash, kill, mémoire saturée), le job resterait `processing` pour toujours. Pour l'éviter, chaque job réservé reçoit un **bail** : `lease_until` = heure de réservation + le `timeout` du job. Une fois le bail dépassé, le job est considéré comme abandonné et un autre worker peut le reprendre.

Le `timeout` est de 600 secondes par défaut (`queue.timeout` dans la config). Un job qui dure légitimement plus longtemps doit le dire, sinon il tournerait deux fois :

```php
class BigExportJob extends MasterJob {
    public function timeout(): int {
        return 3600;
    }
}
```

Comme `attempts` augmente à la réservation (et pas à l'échec), un job qui tue son worker à chaque fois finit quand même `failed` après `max_attempts` essais, avec l'erreur `Lease expired`.

## Faire tourner le worker en continu

Le worker n'est pas censé s'arrêter tout seul. En production, il te faut un superviseur de process qui le relance s'il plante ou si le serveur redémarre (Supervisor, systemd, ou l'équivalent selon ton déploiement). Un exemple avec Supervisor :

```ini
[program:queue-worker]
command=php /chemin/vers/le/projet/craft queue-worker
autostart=true
autorestart=true
```

Il n'y a rien de spécifique au framework là-dedans : `queue-worker` est juste un process PHP normal, longue durée, qu'on garde en vie comme n'importe quel worker.

## Sous le capot

`MasterJob` est lui-même un `EmbeddedDocument` ([09.1](009.1-Persistable.md)). C'est ce qui lui permet d'être stocké tel quel en Mongo et reconstruit avec la bonne sous-classe concrète à la lecture (grâce à `__pclass`), sans repasser par un constructeur qu'il faudrait deviner.

`JobCollection::claimNext()` fait la réservation avec un update en pipeline : les dates (`started_at`, `lease_until`) sont calculées par Mongo lui-même avec `$$NOW` et `$dateAdd`. Tous les workers partagent l'horloge du serveur, et le bail utilise le `timeout` stocké sur le job, sans avoir à le lire avant. `db-sync` crée l'index `{status, created_at}` sur lequel elle s'appuie.

Voir aussi : [18 Les Mails](018-Les-Mails.md) (`SendMailJob`), [09.3 Relations et propagation](009.3-Relations.md) (`SnapshotSyncJob`)
