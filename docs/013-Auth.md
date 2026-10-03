# 13 Auth

## Comment on login et on logout

`Auth` est une façade statique : tu passes par elle partout, et elle délègue au bon mécanisme en coulisses.

```php
Auth::attempt(['email' => $email, 'password' => $password]); // bool
Auth::login($user);   // connecter un utilisateur déjà résolu (ex: juste après une inscription)
Auth::logout();
Auth::user();          // l'utilisateur courant, ou null
Auth::check();          // bool, raccourci de user() !== null
Auth::id();              // son _id, ou null
```

Il n'y a rien de plus à câbler dans un controller. `Auth::attempt()` retrouve le provider (le Document utilisateur), vérifie les identifiants, et connecte si ça passe. Tu peux appeler `Auth::user()` autant de fois que tu veux dans une requête : il ne résout l'utilisateur qu'une fois.

Pour protéger une route derrière l'authentification, ne vérifie pas toi-même dans chaque action : pose `#[Middleware('Auth')]`. Le détail est dans [07 Les Middlewares](007-Les-Middlewares.md).

## Keepers, Gates, Providers

Trois notions empilées, toutes déclarées en config (`auth` + `class.gates`) :

- **Provider** : la classe Document qui représente une identité (`\App\Model\Document\Member::class`, par exemple). Le framework ne connaît que son nom, et il en déduit la Collection par la convention habituelle ([09 Model](009-Model.md)).
- **Gate** : le mécanisme d'authentification lui-même (comment on sait « qui est connecté », comment on login et logout). `SessionGate` est la seule livrée : identifiant + mot de passe, portée par la session PHP, avec un remember-me optionnel (cookie + token).
- **Keeper** : un emplacement nommé qui combine une gate, un provider et des options propres à cette gate. Plusieurs keepers peuvent coexister (un pour le site, un pour une API par token, chacun avec son provider).

```php
'auth' => [
    'default' => 'web',
    'keepers' => [
        'web' => [
            'gate'       => 'session',
            'provider'   => 'member',
            'identifier' => 'email',   // colonne utilisée pour retrouver l'utilisateur
            'remember'   => true,       // active le remember-me côté SessionGate
            'redirect'   => '/login',   // où AuthMiddleware renvoie si non connecté
        ],
    ],
    'providers' => [
        'member' => \App\Model\Document\Member::class,
    ],
],
'class' => [
    'gates' => [
        'session' => \SkankyDev\Auth\Gates\SessionGate::class,
    ],
],
```

`Auth::keeper('web')` résout tout ça. Sans argument, `Auth::keeper()` prend le keeper de `auth.default`, et toutes les méthodes de la façade `Auth::` utilisent ce keeper par défaut. Pour en cibler un précis : `Auth::keeper('api')->check()`, ou `#[Middleware('Auth', 'api')]` côté controller.

Chaque option d'un keeper (`identifier`, `remember`, `redirect`...) est propre à la gate utilisée. `SessionGate` lit `identifier`, `remember` et `redirect`, une autre gate pourrait avoir d'autres besoins (par exemple un nom de header pour une auth par token).

## Ajouter une gate

Tu étends `MasterGate` et tu implémentes `resolve()` (comment retrouver l'utilisateur courant). Si la gate en a besoin, tu surcharges aussi `attempt()`, `login()` et `logout()` :

```php
class ApiTokenGate extends MasterGate {

    protected function resolve(): ?object {
        $token = Request::getInstance()->header('x-api-token');
        if (!$token) {
            return null;
        }
        return $this->provider()->findOne(['api_token' => $token]);
    }

}
```

Depuis n'importe quelle méthode de la gate, `$this->config` (les options du keeper) et `$this->provider()` (la Collection configurée) sont disponibles, ils viennent de `MasterGate`.

Ensuite, tu l'enregistres sous un alias dans `class.gates` et tu l'utilises dans un keeper :

```php
'class' => [
    'gates' => [
        'session'   => \SkankyDev\Auth\Gates\SessionGate::class,
        'api_token' => \App\Auth\Gates\ApiTokenGate::class,
    ],
],
'auth' => [
    'keepers' => [
        'api' => ['gate' => 'api_token', 'provider' => 'member'],
    ],
],
```

Tu n'as pas à te soucier d'être appelé plusieurs fois : `resolve()` n'est exécuté qu'une fois par requête, `MasterGate` met le résultat en cache.

## Sous le capot

`Auth::user()` résout l'utilisateur une seule fois par requête et le met en cache sur l'instance de la gate, quelle que soit la gate. Les keepers résolus sont eux aussi gardés en cache (statique, par nom de keeper).
