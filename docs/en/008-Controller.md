# 08 Controller

A controller is a class that extends `MasterController`, with one method per action. For now `MasterController` doesn't do anything special: it's just the common base, there for the day I need to hook something shared onto it.

```php
namespace App\Controller;

use App\Model\Document\Post;
use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Request;

class PostController extends MasterController {
    public function show(Request $request, Post $post) {
        return view('post.show', ['post' => $post]);
    }
}
```

## Dependency injection

In an action, you just ask for what you need as a parameter, and it shows up on its own. No need to go fetch `Request::getInstance()` or parse the URL by hand:

```php
namespace App\Controller\Auth;

use App\Model\Document\User;
use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Request;

class PasswordController extends MasterController {
    public function reset(Request $request, User $user, string $token) {
        // $user    → fetched from the ID in the URL (/password/reset/:user/:token)
        // $token   → the :token segment, as is
        // $request → the current Request
    }
}
```

It also works in the controller's constructor: ask for a Collection as a parameter, it shows up on its own.

If you want to know how it guesses what goes where, it's in "Under the hood" just below.

## Attaching middlewares

With the `#[Middleware(...)]` attribute, on the class (all actions) or on a method (that action, on top of the class ones):

```php
namespace App\Controller;

use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Middleware\Attribute\Middleware;
use SkankyDev\Http\Request;

#[Middleware('Auth')]
class AdminController extends MasterController {

    #[Middleware('RateLimit', 'login', 5, TIME_MINUTE * 15)]
    public function login(Request $request) { ... }
}
```

The details (the shipped middlewares, how to write one, the `setMiddlewares()` alternative on a declared route) are in [07 Middlewares](007-Middlewares.md).

## Under the hood

The controller is instantiated, and its action called, by the `MasterFactory`: `Application::run()` does `MasterFactory::_make($controller)` then `MasterFactory::_call($controller, $action, $params)`. It's the one that guesses, through reflection, what to put in each typed parameter, in this order:

1. **a subclass of `MasterDocument`** (`Post $post`, `User $user`...): it takes the ID from the route params (by name if you named the segment `:post`, otherwise by position) and does `Post::find($id)`. Not found: `ModelNotFoundException` (404).
2. **a named key exists in the params**: used as is.
3. **a native type, or no type** (`string $token`, `int $page`...): the positional value from the route params, otherwise the parameter's default value if you set one, otherwise it crashes (`ClassNotFoundException`).
4. **any other class** (`Request $request`, a Collection...): resolved recursively through `MasterFactory::make()`.

Collections and `Request` are singletons, so `make()` takes the `getInstance()` shortcut rather than trying to instantiate them.
