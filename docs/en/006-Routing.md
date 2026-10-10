# 06 Routing

It's interpreting the URI so the dispatcher knows what to do. The framework offers two ways: by convention, or by definition.

## The convention

The pattern is simple: `http://example.com/{:module}/{:controller}/{:action}/{:param1}/.../{:paramN}`

The module can be left out if you want to land in the default module (App). Same for the action, it defaults to `index`.

For example, the URL

`http://mydomain.com/persona/show/6a256b9f4f04f94e41005473`

will go to `PersonaController::show`, passing it `6a256b9f4f04f94e41005473` as a parameter.

If no predefined route matches the URI, we fall back on the convention above — that's how the controller/action are found when you haven't declared anything explicitly.

The framework remembers the module of the current route on its own (`Config::getCurrentNamespace()`), so you don't need to put `'namespace' => 'Admin'` back in the link array on every `$this->url(...)` as long as you stay in the same module. It's internal plumbing, if you want the details they're in [04 Config](004-Config.md).


## Predefined routes

They're defined in the `routes/routes.php` file.

We call the router to create them easily. It uses the singleton trait, so you can prefix the methods with `_` to call them more easily.

```php
use SkankyDev\Http\Routing\Router;
use App\Controller\HomeController;

Router::_add('/', [
	'controller' => HomeController::class,
	'action'     => 'index',
])->setName('home');
```

And so, `http://mydomain.com/` will send the application to `HomeController::index`.

The `Router::add` method takes three parameters: the desired URI, the link array, and the regex validation rules.

The `controller` can be given as a full name (`DashboardController::class`, the most convenient) or as a short name (`'Dashboard'`, resolved in the link's `namespace`). With the full name, the module is deduced from the class: `Admin\Controller\DashboardController` gives the `Admin` namespace, no need to specify it. Either way, the route internally keeps the same format as a convention link (`'controller' => 'Dashboard'`). That's what lets `$this->url(['action' => 'x'])` work on a page served by a declared route, and a `['namespace' => 'Admin', 'controller' => 'Dashboard']` link land back on `/admin`.

Example with a password reset URL:

```php
Router::_add('/password/reset/:user/:token', [
	'controller' => PasswordController::class,
	'action'     => 'reset',
], [
	'user'  => '[a-f0-9]{24}',
	'token' => '[a-f0-9]{32}',
])->setName('password-reset');
```

You can also add middlewares directly on the route with the `setMiddlewares` method. It's a list, you can give either an alias declared in `class.middlewares`, or the full class name directly:

```php
Router::_add('/admin', [...])->setMiddlewares(['Auth', 'Session']);
```

And if the middleware needs arguments (like `RateLimit`, which takes a key, a number of attempts and a duration), same syntax as with the `#[Middleware(...)]` attribute on a controller, but as key => array:

```php
Router::_add('/login', [...])->setMiddlewares([
	'RateLimit' => ['login', 5, TIME_MINUTE * 15],
]);
```

See [07 Middlewares](007-Middlewares.md) for the details (attributes on controllers/actions, aliases, etc).
