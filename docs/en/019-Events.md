# 19 Events

## Why

A comment gets posted, and you want to give its author a trophy, notify the admin, clear a cache… If you put all of that in the comments controller, it ends up knowing half of the app. With an event, the controller only says "a comment was just created", and whoever cares takes it from there. That's the Observer pattern: the one emitting doesn't know who's listening, and doesn't care.

Three pieces, all in `SkankyDev\Event`:

- the **event**, a class extending `MasterEvent` that carries the context;
- the **listener**, a class implementing `ListenerInterface` that says which events it listens to;
- the **`EventManager`**, which connects the two.

## The event

A small class, with what the listeners will need:

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

Its name is its class: you listen to `CommentCreated::class`, not to a string you'll end up mistyping sooner or later. `getName()` returns it, and you can override it if one day you want another name.

The properties are up to you: `readonly` if no listener should be able to change them, writable if a listener has to fill something in for the caller (see below). An event is never saved to the database: it lives for the duration of the emit, that's it.

## Emitting

```php
use SkankyDev\Event\EventManager;

EventManager::_emit(new CommentCreated($comment, $user));
```

The listeners are called right away, in order, in the same request. `_emit()` gives you the event back: a listener may have filled it in.

## The listener

```php
namespace App\Listener;

use SkankyDev\Event\ListenerInterface;

class TrophyListener implements ListenerInterface {

    public function __construct(private UserCollection $users) {}

    public static function subscribe(): array {
        return [
            CommentCreated::class => 'onComment',
            PageNotFound::class   => ['onLost', 'log'],   // several methods for one event
        ];
    }

    public function onComment(CommentCreated $event): void {
        // ...
    }
}
```

`subscribe()` returns, for each event, the method(s) to call. Each one receives the event as its only argument.

Then you declare it in the config:

```php
'listeners' => [
    TrophyListener::class => true,
],
```

The class is the key, like the middleware aliases: the config is merged by `array_replace_recursive` ([04 Config](004-Config.md)), and with a numbered list, a project's list would overwrite a module's one slot by slot instead of adding to it. A module can declare its listeners in its own `Config/config.php`, and the project can switch one off by setting it to `false`.

## The framework's events

The framework only emits where your code can't plug in otherwise. Business events (a posted comment, a saved score) are emitted by your application.

| Event | Emitted when | Content |
|---|---|---|
| `SkankyDev\Auth\Event\LoggedIn` | `login()`, a successful `attempt()`, or a session restored by the remember-me cookie | `user`, `remember`, `viaCookie` |
| `SkankyDev\Auth\Event\LoggedOut` | `logout()` while someone was logged in | `user` |
| `SkankyDev\Auth\Event\LoginFailed` | a failed `attempt()` (unknown login, wrong password, empty field) | `identifier`, `login` (never the password) |
| `SkankyDev\Http\Event\ResponseSent` | the response has gone out (not on a redirection nor an error page) | `request`, `response` |
| `SkankyDev\Http\Event\PageNotFound` | a 404 goes out: `notFound()`, or a code 404 `NotFoundException` caught by `Application` (in debug too) | `uri`, `exception` |
| `SkankyDev\Queue\Event\JobFailed` | a job fails for the last time (`max_attempts` reached) | `job`, `jobDoc`, `exception` |

A few details:

- With the remember-me cookie, the session is restored while the user is being resolved. `LoggedIn` is only emitted **once that resolution is done**: a listener calling `Auth::user()` gets the user without starting over.
- If you redefine `notFound()` in your project, your version must emit `PageNotFound` too.
- `ResponseSent` is for work the visitor doesn't have to wait for (stats, logs...). Under PHP-FPM the connection is already closed (`fastcgi_finish_request()`); elsewhere the listener just runs before the end of the script. The session is closed: `Session::get()` still works, but what `Session::set()` writes is lost. A listener's exception is logged, never shown.

## Plugging a callable by hand

For a test, or a one-off case in `config/bootstrap.php`:

```php
EventManager::_listen(CommentCreated::class, function (CommentCreated $event) {
    // ...
});
```

The config listeners go first, then the `_listen()` ones, in the order they were added. `EventManager::_hasListeners(CommentCreated::class)` tells you whether anyone is listening, to avoid preparing an expensive event for nothing.

## When a listener crashes

Nothing is caught: the exception goes up to whoever emitted, then to the `ExceptionHandler` ([15](015-Error-Handling.md)), like any other. No silent errors. It also means a listener can stop the request: a `NotFoundException` with code 404 gives the 404 page in production.

One exception: `JobFailed`. The worker catches and logs an error from one of its listeners, because letting it go up would kill the worker, and every job after it.

If some work is long or may fail without the request having to suffer for it (a mail, an external call), the listener pushes a job ([18 Queue and Jobs](018-Queue-and-Jobs.md)) instead of doing it itself.

## Under the hood

- `subscribe()` is **static**: the `EventManager` reads it without building the listener. That happens on the **first** `_emit()` of the request, only once. A request that emits nothing pays nothing.
- The listener is only built when one of its events is emitted, by `MasterFactory`: its constructor gets dependency injection, like a controller ([08](008-Controller.md)). It's then **kept for the rest of the request**: two emits, one object.
- A badly declared listener (class not found, missing `ListenerInterface`, method that doesn't exist) throws an `EventException` on the first `_emit()`, not silently.
