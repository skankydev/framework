# 20 Tests

## The scope: SkankyDev, not the App

The tests live in the framework's repository (`skankydev/framework`), not in your project. The suite protects the **reusable core**, the framework's `src/` folder, not the business code of a given project. It's written in black and white in its `phpunit.xml`:

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

My reasoning: the framework is meant to stay stable and be reused from one project to the next, so it's the one that deserves real coverage. The application code (`src/App`) changes with every project and has its own needs. That doesn't mean "no tests on the App side", just that this particular suite isn't meant to cover them.

## Two suites

```xml
<testsuite name="SkankyDev">
    <directory>tests/SkankyTest/TestCase</directory>
</testsuite>
<testsuite name="Integration">
    <directory>tests/SkankyTest/Integration</directory>
</testsuite>
```

- **`TestCase/`**: the unit tests, with no external dependency at all (no Mongo, no filesystem beyond the bare minimum). That's the vast majority of the suite.
- **`Integration/`**: they need a real running MongoDB instance (the Collections, the real CRUD, snapshot propagation, see [09.3](009.3-Relations.md)).

## `tests/bootstrap.php`

The tests don't load the project's real config. `bootstrap.php` sets up a minimal config by hand (**not** `Config::initConf()`), just what the tested classes need:

```php
Config::$conf = [];
Config::set('default.namespace', 'App');
Config::set('view.folder', __DIR__ . '/App/View');
Config::set('class.fields', [...]);
// ...
```

A consequence to keep in mind: if you add a config key that a framework class needs (like `view.layout` or `Module`, added along the way), remember to add it to this bootstrap too. Otherwise the tests that go through that class crash with an unexpected `null`, with no apparent link to what you just changed.

## The commands

```bash
composer test              # phpunit, the whole suite
composer coverage          # phpunit --coverage-text
composer coverage-pretty   # phpunit --coverage-html build/coverage
```

To be more targeted:

```bash
php vendor/bin/phpunit tests/SkankyTest/TestCase/Http/UrlBuilderTest.php  # one file
php vendor/bin/phpunit --filter testBuildFromNamedRoute                   # one test
```

## The routine

After any change in the framework's `src/`: `composer test`, never `php -l`. `php -l` only checks the syntax of a single file. The suite catches both the syntax (it crashes right away if a file doesn't parse) and behavior regressions, which a simple lint will never see.
