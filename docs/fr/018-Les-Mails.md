# 18 Les Mails

## L'objet mail

Un mail de ton appli, c'est une classe qui étend `MasterMail` : un sujet, des données, et une vue qui s'en sert (en dot-notation comme n'importe quelle vue, mais **sans le layout du site**) :

```php
class WelcomeMail extends MasterMail {
    protected string $view = 'mail.welcome';

    public function __construct(string $name, string $verifyUrl) {
        $this->data = ['name' => $name, 'verifyUrl' => $verifyUrl];
        $this->addAttachment('/chemin/vers/cgu.pdf', 'CGU.pdf');
    }

    public function subject(): string {
        return 'Bienvenue !';
    }
}
```

Le rendu (`content()`) est appelé par le sender, jamais par toi : il rend la vue `mail.welcome` avec `$data`, sans layout. Un mail HTML n'a pas besoin du header, de la nav ni du footer du site, juste de son propre contenu.

Une règle à retenir : ne mets dans `$data` que des valeurs simples (des strings, des nombres), jamais un Document complet. Un mail peut partir dans un job, et tout ce qui part dans un job traîne dans la collection `jobs` jusqu'à son traitement ([17 La Queue et les Jobs](017-Queue-et-Jobs.md)).

## Le sender

`MailSender` envoie un `MasterMail` par SMTP (PHPMailer en dessous), configuré avec la clé `smtp` :

```php
'smtp' => [
    'host'           => getenv('MAIL_HOST'),
    'port'           => getenv('MAIL_PORT'),
    'secure'         => getenv('MAIL_SECURE'),   // 'tls', 'ssl', ou vide
    'username'       => getenv('MAIL_USERNAME'),
    'password'       => getenv('MAIL_PASSWORD'),
    'default_sender' => getenv('MAIL_SENDER'),
],
```

C'est un singleton, comme les Collections, donc même convention d'appel statique avec `_` :

```php
MailSender::_send($email, new WelcomeMail($name, $verifyUrl));
MailSender::_send([$email1, $email2], $mail); // plusieurs destinataires
```

En cas d'échec d'envoi (SMTP mal configuré, connexion refusée...), tu reçois une `MailException`, pas un `false` silencieux.

## Le job pour la queue

Envoyer un mail, c'est lent (il faut ouvrir une connexion SMTP). Tu ne veux pas faire attendre la requête qui vient de le déclencher (une inscription, une action de l'utilisateur). `SendMailJob` enveloppe l'appel à `MailSender` pour le faire passer par la queue ([17](017-Queue-et-Jobs.md)) :

```php
Queue::push(new SendMailJob($email, new WelcomeMail($name, $verifyUrl)));
```

Le job ne fait qu'un appel à `MailSender::_send()` dans `run()`. C'est le worker qui l'exécute en tâche de fond, et la requête HTTP d'origine ne l'attend pas.

## Sous le capot

`MasterMail` étend `EmbeddedDocument` ([09.1](009.1-Persistable.md)). C'est ce qui permet à un mail concret (`WelcomeMail` ou une autre sous-classe) d'être stocké tel quel comme contenu d'un job, puis reconstruit avec la bonne classe à la relecture. D'où la règle sur `$data`.
