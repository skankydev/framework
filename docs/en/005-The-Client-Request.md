# 05 The Client Request

Everything that comes into the app (GET, POST, JSON, cookies, files, headers...) goes through a single class: `Request`. I did it that way so I wouldn't be chasing `$_GET`, `$_POST` and `$_SERVER` all over the place.

Good news, you almost never need to go fetch it: you ask for it as a parameter of your action and it shows up on its own.

```php
public function show(Request $request, Post $post) { ... }
```

(If you really need it somewhere else, it's a singleton: `Request::getInstance()`.)

## The basics

Who's calling, from where, with what:

```php
use SkankyDev\Http\Request;
$request = Request::getInstance();
$request->method();      // 'GET', 'POST', ...
$request->uri();         // '/module/show/abc' (without the query string)
$request->fullUri();     // same but with the ?thing=stuff
$request->scheme();       // 'http' or 'https'
$request->host();        // 'skankyblog.local'
$request->ip();          // handles X-Forwarded-For if you're behind a proxy
$request->userAgent();
```

And three little checks that save you from digging through the headers by hand:

```php
$request->isAjax();      // X-Requested-With header
$request->isJson();      // Content-Type: application/json
$request->wantsJson();   // Accept: application/json
```

`wantsJson()` is the most useful one: it's what lets the same action answer JSON to an AJAX call and HTML to regular browsing (see [11 Responses](011-Responses.md)).

## Post, Get, and files

Each has its own method, with a default value if you want one:

```php
$request->query('page', 1);    // a GET parameter
$request->post('email');       // a POST parameter
$request->file('avatar');      // an uploaded file
$request->header('accept');    // a header, in lowercase
$request->cookie('session_id');
```

Without a key, you get the whole array: `$request->query()` gives you all of `$_GET`.

And if you don't care where the value came from, there's `input($key, $default)`: it looks in the POST, then the JSON body, then the GET, in that order. There's even a magic shortcut, `$request->email` does `input('email')` behind the scenes.

## Uploaded files

`$request->file('avatar')` gives you an `UploadedFile` object, or `null` if the field doesn't exist or was left empty. For a multiple field (`name="docs[]"`), you get a list of `UploadedFile`.

```php
$file = $request->file('img');
if ($file && $file->isValid()) {
    $persona->img = $file->store('img');   // StoredFile, stored in {upload.folder}/img/
}
```

What the object can tell you:

- `isValid()` / `errorMessage()`: did the upload work? If not, why (file too big for the server, partial upload…). PHP's `UPLOAD_ERR_*` codes are translated into the current language (`skankydev` domain, see [21](021-I18n.md)).
- `mimeType()`: the **real** type, read from the file content (`finfo`), not the one announced by the browser.
- `extension()`: the extension deduced from that real type (`jpg`, `png`, `pdf`…, `bin` if unknown).
- `isImage()`, `dimensions()`: `[width, height]` for an image.
- `clientName()`, `clientExtension()`, `size()`: the original name and the size, for display.
- `store($subfolder = '', $name = null)`: moves the file into a subfolder of the upload folder (created if needed) under a random name (or `$name`) + the safe extension, and gives you back a `StoredFile`. The subfolder is relative (`'img'`, `'media/2026'`): a `..` is rejected, there's no way out of the upload folder.

The upload folder and its public URL come from the config ([04](004-Config.md)), to override in `master.config.php` if needed:

```php
'upload' => [
    'folder' => UPLOAD_FOLDER,   // public/upload by default
    'url'    => '/upload',       // or 'https://cdn.mydomain.com' the day you move to a CDN
],
```

Why so much distrust? Because anything coming from the browser can be faked. If we kept the extension of the name that was sent, a `photo.php` would land in the upload folder (served by the web server) and become executable: anyone could run PHP on your server. Here, the extension comes from the content, so a script disguised as an image ends up as `.bin` or `.txt`, never `.php`. SVG is deliberately missing from the list: it can contain JavaScript.

### StoredFile

`StoredFile` is an `EmbeddedDocument` ([09.1](009.1-Persistable.md)): you store it as is in a document, and Mongo gives it back typed when you read it again.

```php
class Persona extends MasterDocument {
    public ?StoredFile $img = null;
}

// in the view
<img src="<?= e($persona->img?->url()) ?>" alt="">
```

It only stores the path relative to the upload folder (`img/3f2a….png`), the original name, the type, the size, and the dimensions for an image. The URL is **never stored**: `url()` computes it with `upload.url` + the path (`url(true)` to get it absolute, with the request's domain). Same for `fullPath()` (the path on disk), with `upload.folder`. You change domains, move the files or switch to a CDN: you change the config, nothing to migrate in the database. There's also `isImage()`, `extension()` and `delete()` to remove the file.

To validate a file (type, size, image), there are the `file`, `image`, `mimes:…` and `max_size:…` rules ([12.2](012.2-Validation.md)).

## The Session

For the session, I made a static class, `Session`, which talks directly to `$_SESSION`. Nested arrays are traversed with dots: `user.id` rather than `$_SESSION['user']['id']`. Shorter, more readable.

```php
use SkankyDev\Utilities\Session;

Session::set('user.id', $id);
Session::get('user.id');
Session::delete('user.id');
Session::insert('log', 'new line');   // pushes into an array, creates it if needed
```

Starting the session, you don't touch it: a middleware takes care of it ([07 Middlewares](007-Middlewares.md)). All you have left to do is read and write in it.

## Inputs and flash messages

A form that fails validation is the classic case: you want to send the user back to the page with what they had typed and the errors. You redirect with `->withErrors(...)->withInput(...)` ([12 Forms](012-Forms.md)) and that puts all of it in the session, for a single request. On the next page, you get it back with:

```php
old('email');           // what they had typed, or '' by default
error('email');         // the field's error, already in a <span class="error">, or '' if none
```

The flash is exactly the same idea but without a form: a message that lives for a single request, like "Logged in successfully". You set it, you redirect, the next page displays it, and poof, it's gone.

```php
// controller side
return redirect(['name' => 'home'])->withFlash('success', 'Logged in successfully');

// view side
$msg = flash(); // ['type' => 'success', 'message' => '...'] or null
```

Careful, once read it's deleted: display it only once per page.

## Under the hood

For the curious, what you don't need to know to use it but that explains why it works the way it does:

- **Files.** `$_FILES` has a horrible structure as soon as there's a multiple field (`name[]`): PHP gives you an array of properties instead of an array of files. `Request` puts everything back the right way in its constructor and turns each file into an `UploadedFile`, hence `file('documents')` giving you a list right away. A field left empty still shows up in `$_FILES` (with the `UPLOAD_ERR_NO_FILE` error): it's dropped, that's why `file()` gives you `null`.
- **Injection.** If `Request` shows up on its own in your actions, it's the `MasterFactory` resolving it (see [08 Controller](008-Controller.md)). It's a singleton, so it takes the `getInstance()` shortcut.
- **Flash.** Everything relies on `Session::getAndClean($path)`: it reads a value and deletes it right after. That's what makes a flash survive only one request.
- **`Session::regenerate()`.** It changes the session ID while keeping the data. Call it on login, so you don't keep an ID issued before authentication (otherwise, it's an open door to session fixation).
