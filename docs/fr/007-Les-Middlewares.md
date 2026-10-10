# 07 Les Middlewares

## Comment ça marche

Un middleware, c'est une couche qui s'intercale entre la requête et le controller. Démarrer la session, vérifier un token CSRF, checker l'auth : tout ce qui doit se passer avant que ton action s'exécute, sans que tu aies à y penser dans chaque action. Chaque middleware implémente `MiddlewareInterface` :

```php
namespace SkankyDev\Http\Middleware;

interface MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed;
}
```

`$next`, c'est la suite : le middleware suivant, ou le controller si c'était le dernier. Tu as deux choix : appeler `$next($request)` pour laisser passer, ou renvoyer directement une `Response` pour tout couper (auth refusée, CSRF invalide...). Dans ce cas le controller n'est jamais atteint.

Pour une requête, le `MiddlewareManager` empile trois sources, dans cet ordre :

1. les **globaux**, déclarés dans la config `middlewares` : ils tournent sur toutes les requêtes, sans exception ;
2. les **attributs** `#[Middleware(...)]` sur le controller (la classe), puis sur l'action (la méthode) ;
3. ceux posés directement sur la route avec `->setMiddlewares([...])` ([06 Le Routing](006-Le-Routing.md)).

Ça s'exécute en oignon : le premier de la liste est le plus extérieur (il passe en premier et finit en dernier), le dernier est collé au controller. Un middleware global comme `Session` enveloppe donc tout, et celui posé sur la route est le plus proche de ton action.

## Les disponibles

| Alias | Ce qu'il fait |
|---|---|
| `Session` | démarre la session. Global, il passe toujours avant tout le reste. |
| `Csrf` | vérifie le token CSRF sur les méthodes qui modifient (POST/PUT/PATCH/DELETE). Global aussi. En cas d'échec : 419 JSON en AJAX, sinon retour arrière + flash ([12.3 CSRF](012.3-CSRF.md)). |
| `PostOnly` | n'autorise que POST sur l'action ciblée. Utile pour les actions qui modifient (un delete, par exemple), parce que le routing par convention ne distingue pas les verbes HTTP : sans ça, elles seraient atteignables en GET. En cas d'échec : 405 JSON, ou retour arrière + flash. |
| `Auth` | protège une route derrière un keeper d'auth ([13 Auth](013-Auth.md)). Si l'utilisateur n'est pas connecté : redirection si `auth.keepers.{keeper}.redirect` existe et que le client veut du HTML, sinon 401 JSON. |
| `RateLimit` | limite le nombre de POST par IP sur une action (login, mot de passe oublié...). Il prend une clé, un nombre max de tentatives et une durée en secondes. |

`Session` et `Csrf` sont globaux par défaut. Les trois autres sont à la demande : tu les poses où tu en as besoin.

Un détail sur `RateLimit` : il ne compte que les POST (afficher le formulaire en GET n'use aucune tentative), et il ne se remet pas à zéro tout seul quand ça réussit. C'est au controller d'appeler `RateLimiter::clear()` avec la même clé.

Les messages (flash et JSON) de ces middlewares passent par les traductions (domaine `skankydev`, [21](021-I18n.md)).

## En ajouter un

Une classe qui implémente `MiddlewareInterface`, rien de plus :

```php
namespace App\Middleware;

use SkankyDev\Http\Middleware\MiddlewareInterface;

class MonMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed {
        // ... ta logique
        return $next($request);
    }
}
```

Si tu veux l'appeler par un alias court plutôt que par le nom complet de la classe (dans un attribut, un `setMiddlewares` ou la liste des globaux), tu le déclares dans `class.middlewares` :

```php
'class' => [
    'middlewares' => [
        'Mon' => \App\Middleware\MonMiddleware::class,
    ],
],
```

Pour qu'il tourne sur toutes les requêtes, tu l'ajoutes aussi dans la clé `middlewares` de la config :

```php
'middlewares' => [
    //...
    'Mon' => 'Mon',
    //...
],
```

Sinon, tu l'attaches au cas par cas, avec un attribut sur un controller ou avec `setMiddlewares()` sur une route déclarée.

## Les attributs, avec les paramètres

`#[Middleware(...)]` se pose sur la classe (il vaut alors pour toutes les actions du controller) ou sur une méthode (cette action seulement, en plus de ceux de la classe). Il est répétable, donc tu peux en empiler plusieurs :

```php
#[Middleware('Auth')]
class AdminController extends MasterController {

    #[Middleware('RateLimit', 'login', 5, TIME_MINUTE * 15)]
    public function login(Request $request) { ... }
}
```

Le premier argument, c'est l'alias (`'RateLimit'`) ou le nom de classe complet (`RateLimitMiddleware::class`), les deux marchent. Tout ce qui suit est passé tel quel au constructeur du middleware : ici `RateLimitMiddleware::__construct(string $key, int $maxAttempts, int $decaySeconds)`.

Si tu poses deux fois exactement le même middleware avec les mêmes arguments (un global et un attribut, par exemple), il ne tourne qu'une fois.

Côté route déclarée, c'est la même logique, mais en `clé => tableau` plutôt qu'en arguments à la suite :

```php
Router::_add('/login', [...])->setMiddlewares([
    'Auth',                                          // sans argument
    'RateLimit' => ['login', 5, TIME_MINUTE * 15],   // avec arguments
]);
```
