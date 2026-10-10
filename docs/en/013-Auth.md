# 13 Auth

## How to log in and log out

`Auth` is a static facade: you go through it everywhere, and it delegates to the right mechanism behind the scenes.

```php
Auth::attempt(['email' => $email, 'password' => $password]); // bool
Auth::login($user);   // log in an already resolved user (e.g. right after signing up)
Auth::logout();
Auth::user();          // the current user, or null
Auth::check();          // bool, shortcut for user() !== null
Auth::id();              // their _id, or null
```

There's nothing more to wire up in a controller. `Auth::attempt()` finds the provider (the user Document), checks the credentials, and logs in if they're correct. You can call `Auth::user()` as many times as you want in a request: it only resolves the user once.

To protect a route behind authentication, don't check it yourself in every action: put `#[Middleware('Auth')]`. The details are in [07 Middlewares](007-Middlewares.md).

## Keepers, Gates, Providers

Three stacked notions, all declared in the config (`auth` + `class.gates`):

- **Provider**: the Document class that represents an identity (`\App\Model\Document\Member::class`, for example). The framework only knows its name, and deduces the Collection from it with the usual convention ([09 Model](009-Model.md)).
- **Gate**: the authentication mechanism itself (how we know "who's logged in", how we log in and out). `SessionGate` is the only one shipped: identifier + password, carried by the PHP session, with an optional remember-me (cookie + token).
- **Keeper**: a named slot that combines a gate, a provider and options specific to that gate. Several keepers can coexist (one for the site, one for a token-based API, each with its provider).

```php
'auth' => [
    'default' => 'web',
    'keepers' => [
        'web' => [
            'gate'       => 'session',
            'provider'   => 'member',
            'identifier' => 'email',   // column used to find the user
            'remember'   => true,       // enables remember-me on the SessionGate side
            'redirect'   => '/login',   // where AuthMiddleware sends you back if not logged in
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

`Auth::keeper('web')` resolves all of that. Without an argument, `Auth::keeper()` takes the `auth.default` keeper, and all the methods of the `Auth::` facade use that default keeper. To target a specific one: `Auth::keeper('api')->check()`, or `#[Middleware('Auth', 'api')]` on the controller side.

Each option of a keeper (`identifier`, `remember`, `redirect`...) is specific to the gate used. `SessionGate` reads `identifier`, `remember` and `redirect`, another gate could have other needs (for example a header name for token-based auth).

## Adding a gate

You extend `MasterGate` and implement `resolve()` (how to find the current user). If the gate needs it, you also override `attempt()`, `login()` and `logout()`:

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

From any method of the gate, `$this->config` (the keeper's options) and `$this->provider()` (the configured Collection) are available, they come from `MasterGate`.

Then you register it under an alias in `class.gates` and use it in a keeper:

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

You don't have to worry about being called several times: `resolve()` only runs once per request, `MasterGate` caches the result.

## Under the hood

`Auth::user()` resolves the user only once per request and caches it on the gate instance, whatever the gate. The resolved keepers are cached too (statically, by keeper name).
