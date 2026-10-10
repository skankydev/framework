# 14 Error handling

## The global handler

Every uncaught exception ends up in the same place: `ExceptionHandler::handle()`. `Application` installs it right in its constructor (`set_exception_handler`), and adds a shutdown function that catches even fatal errors (`E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`) to hand them over as an `ErrorException`. Result: no silent blank page.

```php
public function handle(Throwable $exception): void {
    $this->log($exception);
    $this->debug ? $this->renderDebug($exception) : $this->renderProduction($exception);
}
```

Debug mode comes from the `debug` config (`.env` → `APP_DEBUG`), true or false, read once when `Application` starts.

## Debug

Two templates, picked depending on the mode:

- **`debug.php`**: the exception's class, the message, file:line, the full trace. Useful in dev, never in production (it leaks internal info).
- **`production.php`**: a generic page, nothing exposed.

The paths are configurable (`view.error` and `view.error_layout`). By default, they're the ones shipped with the framework (the Publishable, see [16 Craft and CLI](016-Craft-and-CLI.md)). A project can publish its own and point the config to them, without touching the code.

These pages are in English, hardcoded: they don't go through the translations, so that the error page can't crash in turn because of a broken language file ([21 Internationalization](021-I18n.md)). Exception messages are in English too.

## The exception's code = the HTTP status

It's a convention throughout the framework: the code you pass to the exception is used as the HTTP status.

```php
throw new ModelNotFoundException("Document not found", 404);
```

## The special case of `NotFoundException`

`Application::run()` handles exceptions of the `NotFoundException` family separately (method, controller or data not found: see [09 Model](009-Model.md) and [06 Routing](006-Routing.md)). It's not a crash, it's an expected outcome.

- **Code 404 and not in debug**: not the generic error page, but the real 404 page (`notFound()`, a normal view with layout, CSS and JS, see [11 Responses](011-Responses.md)). The exception is still logged. This page is the `error.404` view, so the `src_front/view/error/404.php` file **of your project**: the framework doesn't provide it (the starter has one), and without it you'll get an error instead of your 404.
- **Otherwise** (debug mode, or a `NotFoundException` reused with a code other than 404): the regular `handle()`, like any exception.

That last distinction matters: a `NotFoundException` with a code other than 404 stays a real error in production, it isn't disguised as a silent 404.

## Under the hood

The two error pages (`debug.php` and `production.php`) are rendered without going through `HtmlView` or `Response`: no `Request`, no routing, no session. That's on purpose: this handler must still work when the rest of the app is broken, since it's the one catching the cases where everything goes haywire.

```php
protected function renderErrorPage(string $template, Throwable $exception): void {
    http_response_code(500);
    $contentPath = Config::get('view.error') . DS . $template;
    $layoutPath  = Config::get('view.error_layout');
    // rendered with a plain include + ob_start, not with HtmlView
}
```

See also: [15 Logs](015-Logs.md)
