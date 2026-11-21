# 19 Les Événements

## Pourquoi

Un commentaire est posté, et tu veux donner un trophée à son auteur, prévenir l'admin, vider un cache… Si tout ça s'écrit dans le controller des commentaires, il finit par connaître la moitié de l'application. Avec un événement, le controller dit seulement « un commentaire vient d'être créé », et ceux que ça intéresse s'en occupent. C'est le pattern Observer : celui qui émet ne sait pas qui écoute.

Trois pièces, toutes dans `SkankyDev\Event` :

- l'**événement**, une classe qui étend `MasterEvent` et transporte le contexte ;
- le **listener**, une classe qui implémente `ListenerInterface` et dit quels événements elle écoute ;
- l'**`EventManager`**, qui fait le lien entre les deux.

## L'événement

Une petite classe, avec ce dont les listeners auront besoin :

```php
namespace App\Event;

use SkankyDev\Event\MasterEvent;

class CommentCreated extends MasterEvent {
    public function __construct(
        public readonly Comment $comment,
        public readonly User $author,
    ) {}
}
```

Son nom, c'est sa classe : on écoute `CommentCreated::class`, pas une chaîne qu'on risque de mal taper. `getName()` le renvoie, et tu peux le surcharger si un jour tu veux un autre nom.

Les propriétés sont libres : `readonly` si tu veux qu'aucun listener ne puisse les modifier, modifiables si un listener doit remplir quelque chose pour l'appelant (voir plus bas). Un événement n'est jamais sauvé en base : il vit le temps de l'émission.

## Émettre

```php
use SkankyDev\Event\EventManager;

EventManager::_emit(new CommentCreated($comment, $user));
```

Les listeners sont appelés tout de suite, dans l'ordre, dans la même requête. `_emit()` te rend l'événement : un listener a pu le compléter.

## Le listener

```php
namespace App\Listener;

use SkankyDev\Event\ListenerInterface;

class TrophyListener implements ListenerInterface {

    public function __construct(private UserCollection $users) {}

    public static function subscribe(): array {
        return [
            CommentCreated::class => 'onComment',
            PageNotFound::class   => ['onLost', 'log'],   // plusieurs méthodes pour un événement
        ];
    }

    public function onComment(CommentCreated $event): void {
        // ...
    }
}
```

`subscribe()` renvoie, pour chaque événement, la ou les méthodes à appeler. Chacune reçoit l'événement en seul argument.

Puis tu le déclares dans la config :

```php
'listeners' => [
    TrophyListener::class => true,
],
```

La classe est la clé, comme pour les alias de middlewares : la config est fusionnée par `array_replace_recursive` ([04 Config](004-Config.md)), et avec une liste numérotée, la liste d'un projet écraserait celle d'un module case par case au lieu de s'y ajouter. Un module peut déclarer ses listeners dans son propre `Config/config.php`, et le projet peut en couper un en le passant à `false`.

## Les événements du framework

Le framework n'émet que là où ton code ne peut pas se brancher autrement. Les événements métier (un commentaire posté, un score enregistré), c'est ton application qui les émet.

| Événement | Émis quand | Contenu |
|---|---|---|
| `SkankyDev\Auth\Event\LoggedIn` | `login()`, `attempt()` réussi, ou session restaurée par le cookie remember-me | `user`, `remember`, `viaCookie` |
| `SkankyDev\Auth\Event\LoggedOut` | `logout()` alors que quelqu'un était connecté | `user` |
| `SkankyDev\Auth\Event\LoginFailed` | `attempt()` raté (login inconnu, mauvais mot de passe, champ vide) | `identifier`, `login` (jamais le mot de passe) |
| `SkankyDev\Http\Event\ResponseSent` | la réponse est partie (pas sur une redirection ni une page d'erreur) | `request`, `response` |
| `SkankyDev\Http\Event\PageNotFound` | une 404 part : `notFound()`, ou une `NotFoundException` code 404 attrapée par `Application` (en debug aussi) | `uri`, `exception` |
| `SkankyDev\Queue\Event\JobFailed` | un job échoue pour la dernière fois (`max_attempts` atteint) | `job`, `jobDoc`, `exception` |

Quelques détails :

- Avec le cookie remember-me, la session est restaurée pendant la résolution de l'utilisateur. `LoggedIn` n'est émis qu'**une fois cette résolution finie** : un listener qui appelle `Auth::user()` récupère l'utilisateur sans tout relancer.
- Si tu redéfinis `notFound()` dans ton projet, ta version doit émettre `PageNotFound` elle aussi.
- `ResponseSent` sert au travail que le visiteur n'a pas à attendre (stats, logs…). Sous PHP-FPM, la connexion est déjà fermée (`fastcgi_finish_request()`) ; ailleurs, le listener tourne juste avant la fin du script. La session est fermée : `Session::get()` marche toujours, mais ce qu'on écrit avec `Session::set()` est perdu. Une exception d'un listener est loggée, jamais affichée.

## Brancher un callable à la main

Pour un test, ou un cas ponctuel dans `config/bootstrap.php` :

```php
EventManager::_listen(CommentCreated::class, function (CommentCreated $event) {
    // ...
});
```

Les listeners de la config passent d'abord, puis ceux de `_listen()`, dans l'ordre où ils ont été ajoutés. `EventManager::_hasListeners(CommentCreated::class)` te dit si quelqu'un écoute, pour éviter de préparer un événement coûteux pour rien.

## Quand un listener plante

Rien n'est attrapé : l'exception remonte jusqu'à celui qui a émis, puis jusqu'à l'`ExceptionHandler` ([15](015-Gestion-des-Erreurs.md)), comme n'importe quelle autre. Pas d'erreur silencieuse. Ça veut aussi dire qu'un listener peut arrêter la requête : une `NotFoundException` avec le code 404 donne la page 404 en production.

Seule exception : `JobFailed`. Le worker attrape et logue l'erreur d'un de ses listeners, parce que la laisser remonter tuerait le worker, et avec lui tous les jobs suivants.

Si un traitement est long ou peut échouer sans que la requête doive en pâtir (un mail, un appel externe), le listener pousse un job ([18 La Queue et les Jobs](018-Queue-et-Jobs.md)) au lieu de le faire lui-même.

## Sous le capot

- `subscribe()` est **statique** : l'`EventManager` le lit sans construire le listener. Ça se fait au **premier** `_emit()` de la requête, une seule fois. Une requête qui n'émet rien ne paie rien.
- Le listener n'est construit que quand un de ses événements est émis, par `MasterFactory` : son constructeur profite de l'injection de dépendances, comme un controller ([08](008-Controller.md)). Il est ensuite **gardé pour le reste de la requête** : deux émissions, un seul objet.
- Un listener mal déclaré (classe introuvable, `ListenerInterface` absente, méthode qui n'existe pas) lève une `EventException` au premier `_emit()`, pas en silence.
