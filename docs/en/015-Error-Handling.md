# 15 Error handling

## The global handler

Every uncaught exception ends up in the same place: `ExceptionHandler::handle()`. `Application` installs it right in its constructor (`ExceptionHandler::register()`: `set_exception_handler`), and adds a shutdown function that catches even fatal errors (`E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`) to hand them over as an `ErrorException`. Result: no more blank page with no explanation.

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

The paths are configurable (`view.error` and `view.error_layout`). By default, they're the ones shipped with the framework (the Publishable, see [17 Craft and CLI](017-Craft-and-CLI.md)). A project can publish its own and point the config to them, without touching the code.

These pages are in English, hardcoded: they don't go through the translations, so that the error page can't crash in turn because of a broken language file ([12 Internationalization](012-I18n.md)). Exception messages are in English too.

The arguments of each call in the trace are rendered by `debug()` with tight limits (depth, number of entries, string length): a trace carrying a big object graph stays a light page.

## Warnings, notices and deprecations

`ExceptionHandler::register()` also installs `handleError()` (`set_error_handler`): a non-fatal PHP error is never silent.

- **Always logged** to the error file, once per request for a given message/file/line (a warning in a loop doesn't flood the log).
- **In debug**: displayed in place, in a `.debug-message.debug-error` block (same CSS classes as `debug()`), instead of PHP's raw output. Not displayed for a JSON request, the block would break the response.
- **Out of debug**: nothing is displayed.

The `@` operator and `error_reporting` are still honoured.

## `debug()` and the `Dumper`

`debug($data, $message = '', $limits = [])` prints a coloured dump and picks its rendering by itself: an HTML block (`debug-*` classes) on a page, ANSI-coloured text in a terminal. The rendering comes from `SkankyDev\Utilities\Dumper` (`html()` / `cli()`), bounded by construction: whatever exceeds a limit is replaced by a `…` marker.

The detection relies on the SAPI (`Dumper::isCli()`). For the cases where it's wrong (a test, a CLI script building HTML), `Dumper::forceMode('html')` or `'cli'` imposes one, `forceMode(null)` hands back to the detection.

| Limit | Default | Role |
|---|---|---|
| `depth` | 6 | nesting levels |
| `items` | 100 | entries per array / object |
| `string` | 2000 | characters per string |
| `nodes` | 5000 | values in total |

```php
debug($paginator, 'scores', ['depth' => 3, 'items' => 10]);
```

## In CLI

`CliApplication` registers the same handler, in terminal mode (`new ExceptionHandler($debug, cli: true)`). A crashing `craft` command no longer prints PHP's raw output:

- **Uncaught exception or fatal error**: class, message and file:line in colour, plus the trace in debug. Logged like on the web side, with the command line instead of the URL.
- **Warning, notice, deprecation**: one coloured line, displayed even out of debug (whoever runs the command is entitled to see it), and logged.

## The exception's code = the HTTP status

It's a convention throughout the framework: the code you pass to the exception is used as the HTTP status.

```php
throw new ModelNotFoundException("Document not found", 404);
```

## The special case of `NotFoundException`

`Application::run()` handles exceptions of the `NotFoundException` family separately (method, controller or data not found: see [10 Model](010-Model.md) and [06 Routing](006-Routing.md)). It's not a crash, it's an expected outcome.

- **Code 404 and not in debug**: not the generic error page, but the real 404 page (`notFound()`, a normal view with layout, CSS and JS, see [09 Responses](009-Responses.md)). The exception is still logged. This page is the `error.404` view, so the `src_front/view/error/404.php` file **of your project**: the framework doesn't provide it (the starter has one), and without it you'll get an error instead of your 404.
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

See also: [16 Logs](016-Logs.md)
