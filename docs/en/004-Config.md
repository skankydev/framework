# 04 Config

## The .env

There's a `.env.dist` file in the project. Copy it and rename it to `.env` to hold the secret settings, the ones that depend on the environment.

## How to get a value

The `Config` class is a static class. It gathers the different config arrays: `master.config.php`, found in the `config` folder at the project root, and the ones from the different modules, in `{Module}/Config/config.php`.

It uses the `ArrayPathable` trait, which makes big arrays easier to work with: you access a value with a dotted path.

```php
use SkankyDev\Config\Config;

$value = Config::get('path.to.value');

Config::set('path.to.value', $value);
```

## The different files

The config is built by `Config::initConf()`, called only once at startup (by `Application` or `CliApplication`). It merges three sources, and each one overwrites the previous:

1. `default.config.php`, in the framework (`vendor/skankydev/framework/src/Config/`): the framework's defaults;
2. `src/{Module}/Config/config.php`: the config of each module declared in `Module` (for now, just `App`);
3. `config/master.config.php`: the project's config, which always has the last word.

In short: you never need to touch the framework's defaults. If you want to change a value, put it back in your `master.config.php`, and yours wins.

## Attaching classes

Some parts of the framework (forms, validation, middlewares...) don't want a full class name all over the code. They go through an alias declared in the config, which is turned into a class when it's used.

```php
'class' => [
    'fields'      => [
        'editorjs' => \App\Form\Fields\EditorJsField::class,
    ],
    'rules'       => [
        // validation rule alias => class
    ],
    'middlewares' => [
        // alias used in #[Middleware('Alias', ...)] => class
    ],
],
```

- `class.fields`: the field types you can use in a `FormBuilder` (`$this->add('slug', 'editorjs')`).
- `class.rules`: the validation rules you can use in `$form->validate()`.
- `class.middlewares`: alias → class, used by the middleware pipeline and by the `#[Middleware('Alias', ...)]` attribute.

It saves you from retyping the full namespace every time, and it lets you change the implementation behind an alias without touching the code that uses it. The other aliases of the same kind (`class.gates`, `class.parts`) are explained in their chapters ([13 Auth](013-Auth.md), [10 View](010-View.md)).

## Getting information and using it

On top of `Config::get()` and `Config::set()`, there are shortcuts for the values you often look up:

```php
Config::getDbConf('default');      // mongo connection config
Config::getModuleList();           // ['App', ...] the list of active modules
Config::getDefaultNamespace();     // 'App'
Config::getDefaultAction();        // 'index'
Config::getDebug();                // debug level
Config::getVersion();              // skankydev version
```

There's also `Config::getCurrentNamespace()` and `Config::setCurrentNamespace()`. They remember the module of the current route: that's what saves you from passing `'namespace' => 'Admin'` to every `$this->url(...)` while you stay in the same module ([06 Routing](006-Routing.md)). Without a current module, the default module is used.

## Under the hood

`Config::initConf()` does the merge with `array_replace_recursive`, in this order: the framework's defaults, then each module's config (in the order of the `Module` list, so a module declared later overwrites one declared earlier), then `master.config.php`.

Your `master.config.php` doesn't need anything special: it simply returns its array (`return [...]`, or `$conf = [...]; return $conf;`, both work). A module without a `Config/config.php` file is simply skipped.
