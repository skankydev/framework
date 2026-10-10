# 07 Middlewares

## How it works

A middleware is a layer that sits between the request and the controller. Starting the session, checking a CSRF token, checking auth: everything that has to happen before your action runs, without you having to think about it in every action. Every middleware implements `MiddlewareInterface`:

```php
namespace SkankyDev\Http\Middleware;

interface MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed;
}
```

`$next` is what comes next: the next middleware, or the controller if this was the last one. You have two choices: call `$next($request)` to let it through, or return a `Response` directly to cut everything short (auth refused, invalid CSRF...). In that case the controller is never reached.

For a request, the `MiddlewareManager` stacks three sources, in this order:

1. the **global** ones, declared in the `middlewares` config: they run on every request, no exception;
2. the `#[Middleware(...)]` **attributes** on the controller (the class), then on the action (the method);
3. the ones set directly on the route with `->setMiddlewares([...])` ([06 Routing](006-Routing.md)).

It runs like an onion: the first in the list is the outermost (it goes first and finishes last), the last one is right against the controller. A global middleware like `Session` therefore wraps everything, and the one set on the route is the closest to your action.

## The available ones

| Alias | What it does |
|---|---|
| `Session` | starts the session. Global, it always runs before everything else. |
| `Csrf` | checks the CSRF token on methods that modify things (POST/PUT/PATCH/DELETE). Global too. On failure: 419 JSON for AJAX, otherwise back to the previous page + flash ([12.3 CSRF](012.3-CSRF.md)). |
| `PostOnly` | only allows POST on the targeted action. Useful for actions that modify things (a delete, for example), because convention-based routing doesn't tell HTTP verbs apart: without it, they'd be reachable with GET. On failure: 405 JSON, or back to the previous page + flash. |
| `Auth` | protects a route behind an auth keeper ([13 Auth](013-Auth.md)). If the user isn't logged in: redirect if `auth.keepers.{keeper}.redirect` exists and the client wants HTML, otherwise 401 JSON. |
| `RateLimit` | limits the number of POSTs per IP on an action (login, forgotten password...). It takes a key, a maximum number of attempts and a duration in seconds. |

`Session` and `Csrf` are global by default. The other three are on demand: you put them where you need them.

A detail about `RateLimit`: it only counts POSTs (showing the form with GET doesn't use up any attempt), and it doesn't reset itself on success. It's up to the controller to call `RateLimiter::clear()` with the same key.

The flash and JSON messages of these middlewares go through the translations (`skankydev` domain, [21](021-I18n.md)).

## Adding one

A class that implements `MiddlewareInterface`, nothing more:

```php
namespace App\Middleware;

use SkankyDev\Http\Middleware\MiddlewareInterface;

class MyMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed {
        // ... your logic
        return $next($request);
    }
}
```

If you want to call it by a short alias rather than by the full class name (in an attribute, a `setMiddlewares` or the list of globals), you declare it in `class.middlewares`:

```php
'class' => [
    'middlewares' => [
        'My' => \App\Middleware\MyMiddleware::class,
    ],
],
```

For it to run on every request, you also add it to the `middlewares` key of the config:

```php
'middlewares' => [
    //...
    'My' => 'My',
    //...
],
```

Otherwise, you attach it case by case, with an attribute on a controller or with `setMiddlewares()` on a declared route.

## Attributes, with parameters

`#[Middleware(...)]` goes on the class (it then applies to all of the controller's actions) or on a method (that action only, on top of the class ones). It's repeatable, so you can stack several:

```php
#[Middleware('Auth')]
class AdminController extends MasterController {

    #[Middleware('RateLimit', 'login', 5, TIME_MINUTE * 15)]
    public function login(Request $request) { ... }
}
```

The first argument is the alias (`'RateLimit'`) or the full class name (`RateLimitMiddleware::class`), both work. Everything after it is passed as is to the middleware's constructor: here `RateLimitMiddleware::__construct(string $key, int $maxAttempts, int $decaySeconds)`.

If you set the exact same middleware twice with the same arguments (a global one and an attribute, for example), it only runs once.

On a declared route, it's the same logic, but as `key => array` rather than arguments one after another:

```php
Router::_add('/login', [...])->setMiddlewares([
    'Auth',                                          // no argument
    'RateLimit' => ['login', 5, TIME_MINUTE * 15],   // with arguments
]);
```
