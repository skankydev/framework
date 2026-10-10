# 03 Introduction

SkankyDev is a small PHP framework built on the good old MVC pattern.
It's used to build CRUD applications quickly and focus on the business logic, with peace of mind.

Before diving into the chapters, a quick tour: what happens when a request comes in, where things are stored, and how a project is split into modules.

## The journey of a request

When someone opens a page of your site, here's what happens, in order:

1. `public/index.php` starts the autoload, then `Bootstrap::boot()`, the framework's startup (the `.env`, the config, the timezone), then `config/bootstrap.php`, your application's startup (choosing the language, see [21](021-I18n.md)). Finally, it runs `Application::run()`.
2. The config is assembled ([04 Config](004-Config.md)) and the error handler is set up ([14](014-Error-Handling.md)).
3. The request is read into a `Request` object ([05](005-The-Client-Request.md)).
4. The router looks for the route: first the ones you declared in `routes/routes.php`, otherwise it falls back to the `/module/controller/action/params` convention ([06 Routing](006-Routing.md)).
5. The middlewares run, one by one ([07](007-Middlewares.md)): the session, CSRF, and the ones you added.
6. The controller is built and its action called ([08](008-Controller.md)). The action's parameters are guessed automatically: the `Request`, a Collection, or a Document fetched from the ID in the URL.
7. The action returns a `Response` ([11](011-Responses.md)): a view, a redirect, or JSON. The framework sends it.

For the command line (`php craft`), it's the same idea: `craft` starts `CliApplication`, which assembles the same config and then runs the requested command ([16](016-Craft-and-CLI.md)).

## The folder structure

Here's what you get in a project created with the starter ([02 Installation](002-Installation.md)):

```
config/            your project's config
  bootstrap.php      your application's own startup (the language...)
  master.config.php  your config, it always has the last word
public/            the only folder visible from the web
  index.php          the entry point
  dist/              the CSS and JS compiled by Vite
routes/
  routes.php         your hand-declared routes
src/
  App/               your code (that's the "App" module, see below)
src_front/           everything related to display
  js/                the JavaScript
  scss/              the styles
  view/              the PHP templates (views, layouts, parts)
  lang/              the translations, one folder per language (see 21 Internationalization)
logs/              the logs, created when needed
vendor/            the dependencies, including the framework (skankydev/framework)
.env.dist          the template for your .env
composer.json
craft              the command line: php craft
package.json       the front-end dependencies
vite.config.js     the front-end build
LICENSE.txt
```

One thing to notice: **the framework isn't in your project**. It lives in `vendor/skankydev/framework/`, and you don't touch it. The closest thing to it on the project side is `config/master.config.php`, which overrides its default settings.

The framework has its own tests, but they live in its repository, not in yours ([20 Tests](020-Tests.md)).

## Building with modules

A **module** is a folder in `src/` with its own namespace. The starter only has one, `App`, which is the default module. It's declared in the config:

```php
// config/master.config.php
'Module' => ['App'],
```

Inside, every module stores its stuff the same way (everything is optional, you create what you need):

```
src/App/
  Config/config.php   the module's own config
  Controller/         the controllers
  Model/              the Collections, and Document/ for the Documents
  Form/               the forms
  Mail/               the mails
  Middleware/         your middlewares
  View/Part/          the part classes
  Command/            your craft commands
```

The module comes into play in three places:

- **In the URL.** If the first segment matches a module, that's the one used: `/admin/user/index` calls `Admin\Controller\UserController::index`. Otherwise, it's the default module: `/user/index` calls `App\Controller\UserController::index` ([06 Routing](006-Routing.md)).
- **In the config.** Each module's `Config/config.php` file is merged with the rest ([04 Config](004-Config.md)).
- **In `php craft`.** Each module's commands are discovered automatically ([16](016-Craft-and-CLI.md)).

### Creating a module

Let's take an `Admin` module. Three steps:

1. **Declare it** in `config/master.config.php`:
   ```php
   'Module' => ['App', 'Admin'],
   ```
2. **Tell Composer where it is**, in `composer.json`, then rerun the autoload:
   ```json
   "autoload": {
       "psr-4": {
           "App\\": "src/App",
           "Admin\\": "src/Admin"
       }
   }
   ```
   ```bash
   composer dump-autoload
   ```
3. **Put what you need in it**, for example a controller:
   ```php
   namespace Admin\Controller;

   use SkankyDev\Controller\MasterController;

   class DashboardController extends MasterController {
       public function index() {
           return view('admin.dashboard.index');
       }
   }
   ```

And that's it: `/admin/dashboard` calls `Admin\Controller\DashboardController::index` (the default action is `index`). If you add `src/Admin/Config/config.php`, it's merged into the config, and a class in `src/Admin/Command/` shows up in `php craft` on its own.

One thing to know:

- **Views aren't organized by module.** They all stay in `src_front/view/`: `view('admin.dashboard.index')` opens `src_front/view/admin/dashboard/index.php`. It's up to you to put them in a subfolder named after the module, like above.

## What's next

The rest of the docs follows the same order as the journey of a request: we start with the config and the request, go through routing and middlewares, then the controller, the model and the view. Then come forms, authentication, errors, the command line, the queue, mails, the HTTP client and finally the tests. The [table of contents](000-Table-of-Contents.md) gives you all of that at a glance.
