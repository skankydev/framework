# 09 Responses

## That's the response

A controller action always returns a `Response`, never a direct `echo`. To make one, there are three global functions (in `function.php`):

```php
view('article.show', ['article' => $article]);   // an HTML view (or JSON if the client asks for it)
redirect(['name' => 'article-show', 'params' => [$id]]); // 302 + Location
response(['ok' => true]);                          // always JSON, for AJAX
```

Each one gives you a `Response` instance, chainable (`->status()`, `->header()`, `->withFlash()`...), and that's the instance your action returns. Nothing goes out to the browser until the framework has called `->send()` on it, at the end of the pipeline.

## `view()` / `redirect()` / `response()`

- **`view($name, $data)`**: renders a template ([11 View](011-View.md)). It's the only one of the three that has an associated view.
- **`redirect($link)`**: builds the URL with the `UrlBuilder` ([06.1 The UrlBuilder](006.1-UrlBuilder.md)) and answers 302 with the `Location` header. You can chain it with `withFlash()`, `withErrors()` or `withInput()` to prepare the next page.
- **`response($data)`**: a data response, always JSON, never a view. It's for an AJAX endpoint.

## JSON/HTML negotiation

You don't have to write two actions, one for HTML and one for AJAX: `view()` picks on its own depending on who's calling.

- **No associated view** (`response()`): always JSON, no matter what.
- **A view exists** (`view()`) but the client sends `Accept: application/json` (typically an AJAX request, see `wantsJson()` in [05 The Client Request](005-The-Client-Request.md)): JSON wins, the view isn't rendered.
- **Otherwise**: the normal HTML view.

So a single action can serve either a regular page or an AJAX call:

```php
public function show(Article $article) {
    return view('article.show', ['article' => $article]);
    // normal browsing → the article.show view
    // AJAX request (Accept: application/json) → {"article": {...}} as is
}
```

## Handy chaining

Three methods designed to prepare the next page after a `redirect()`. They all go through the Session ([05](005-The-Client-Request.md)):

```php
return redirect(['name' => 'form-page'])
    ->withErrors($form->getErrors())   // the validation errors, read by error()
    ->withInput($input)                // the typed values, read by old()
    ->withFlash('error', 'Fix the fields in red');
```

`withErrors()` and `withInput()` go together: you use them after a form validation failure ([13 Forms](013-Forms.md)). `withFlash()` is for any one-off message (success, error, info), with or without a form.

## Sending a file

`Response::file($file, $name = null, $download = false)` sends a file from the disk: a `StoredFile` (typically a private one, see [05](005-The-Client-Request.md)) or an absolute path.

```php
return Response::file($order->invoice);                        // shown in the browser when it can (image, PDF…)
return Response::file($order->invoice, download: true);        // forced download
return Response::file($path, 'report-2026.csv', download: true);
```

- The type comes from the `StoredFile` (read from the content at upload time), or from `finfo` for a path. The name offered to the browser is the `StoredFile`'s original name, or the file's own, unless you give one.
- HTML, SVG, XML and JavaScript are **always sent as a download**: shown in the page, they could run a script on your domain. `X-Content-Type-Options: nosniff` stops the browser from guessing another type.
- File not found: `NotFoundException` (code 404), so a real 404 in production.
- The file is read with `readfile()` when `send()` runs, it doesn't go through the body in memory.

It's up to you to check beforehand that the person is allowed to see it: `Response::file()` sends whatever you give it.

## Under the hood

### `build()` vs `send()`

`Response::build()` builds the response body:

```php
public function build(): self {
    if(Request::_wantsJson() || $this->viewName === ''){
        // JSON: $this->data encoded as is
    }else{
        // HTML: the view is rendered (HtmlView)
    }
}
```

`build()` prepares the body and the content headers (`Content-Type`) without sending anything. `send()`, called automatically at the end of the pipeline (`Application::run()`), triggers `build()` if needed, then actually writes the response: HTTP code, headers, body.

A detail: `send()` does **not** build a body for a redirect (3xx). There's nothing to display, just the `Location` header and an `exit` once the headers are sent.

```php
if (!$this->built && ($this->statusCode < 300 || $this->statusCode >= 400)) {
    $this->build();
}
```

So `build()` runs for 2xx (normal content) and for 4xx/5xx (an error page has a body), but not in between.

**Safety net**: if headers have already gone out (a stray `echo` before, a PHP warning...) while the response is a redirect, `send()` falls back on a `<script>window.location.href = '...'</script>` rather than crashing on a header that's already been sent.
