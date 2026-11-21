# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/). While in `0.x`, a minor
version (`0.1` → `0.2`) may contain breaking changes: they're listed under **Upgrading**.

## [Unreleased]


## [0.3.2] - 2026-11-21

### Upgrading

- **The remember-me cookie is renamed** `auth_remember_token` (was `auth.remember_token`): users
  logged in with "remember me" have to log in again, once.
- **`Request::ip()` no longer trusts `X-Forwarded-For` by default.** Behind a reverse proxy, list
  its IPs in the new `trusted_proxies` config, or `ip()` returns the proxy's address.

### Added

- `Http\Event\ResponseSent`: emitted by `Application` once the response is sent, for work the
  visitor shouldn't wait for (stats, logs...). Under PHP-FPM the connection is already closed.
  The session is closed too: `Session::get()` still works, `Session::set()` is lost. Not emitted
  for redirections nor error pages, and a listener's exception is logged, never shown.
- `Response::finish()`: writes the session, then closes the connection under PHP-FPM
  (`fastcgi_finish_request()`).
- `Response::getStatus()` and `Response::getContentType()` (without its parameters, header name
  matched case-insensitively).
- `Session::close()`: writes the session and releases its lock.
- `trusted_proxies` config: exact IPs of the reverse proxies allowed to give the client IP.

### Fixed

- Remember-me never worked: PHP turns `.` into `_` in incoming cookie names, so the
  `auth.remember_token` cookie was sent but never read back.
- `Request::ip()` took the first IP of `X-Forwarded-For`, which anyone can forge. The header is
  now read only from a trusted proxy, walking the chain from the right.


## [0.3.1] - 2026-11-15

### Fixed

- `skankydev.version` still said `0.3.0`.


## [0.3.0] - 2026-11-15

### Upgrading from 0.2

- **Everything the app writes moves to `storage/`.** Uploads go to `storage/files/public`
  (`upload.folder`), served through the `public/upload` link; logs go to `storage/logs`
  (`storage.logs`). Run `php craft storage-link`: it creates the folders and the link, and moves
  the content of an existing `public/upload` folder. `upload.url` doesn't change and `StoredFile`
  only stores relative paths: nothing to migrate in the database. In `.gitignore`, replace
  `public/upload/*` with `/public/upload` and `/logs` with `/storage/files/public/*`,
  `/storage/logs/*`, `/storage/cache/*`. The web server must follow symlinks
  (`Options +FollowSymLinks` on Apache).
- **Forms no longer get a default submit button.** A form needs `->submit('save')` (or a
  `submit` field) to show one. `FormBuilder::$submitLabel`, `$submitAttributes` and
  `renderSubmit()` are removed: a form that overrode them must use `submit()` instead.
- **`HtmlView` escapes meta values** (`keywords`, `addMeta()`): pass raw text, not
  already-escaped HTML, or it will be escaped twice.
- `debug()` escapes the values it prints and caps its output (see `Dumper` below).
- Warnings, notices and deprecations now show up (log, and on the page in debug mode): code that
  used to run "quietly" may reveal some.

### Added

- `storage/` folder (`STORAGE_FOLDER`, `PRIVATE_FOLDER`, `LOG_FOLDER`, `CACHE_FOLDER`), configured
  by `upload.link` and `storage.private` / `storage.logs` / `storage.cache`.
- `php craft storage-link`: creates the storage folders and the `public/upload` link.
- `Log::folder()`: the logs folder (`storage.logs`).
- `UploadedFile::storePrivate()`: stores into `storage.private`, out of the web's reach. The
  `StoredFile` gets `private = true`: `fullPath()` follows, `url()` throws a `LogicException`.
- Events (`SkankyDev\Event`): `MasterEvent`, `ListenerInterface` (static `subscribe()`), and
  `EventManager::_emit()` / `_listen()` / `_hasListeners()`. Listeners are declared in the new
  `listeners` config (`Listener::class => true`, `false` to switch one off), built lazily through
  `MasterFactory` and kept for the request.
- Framework events: `Auth\Event\LoggedIn` / `LoggedOut` / `LoginFailed` (`SessionGate`),
  `Http\Event\PageNotFound` (`notFound()` and `Application`), `Queue\Event\JobFailed` (worker,
  last attempt). `notFound()` takes the exception as an optional second argument.
- `Response::file()`: sends a `StoredFile` or a path (inline or download, UTF-8 file name, HTML/SVG
  always downloaded, `NotFoundException` 404 when missing). `Response::getHeaders()`.
- `SkankyDev\Utilities\Dumper`: bounded, coloured dump of any value (`html()` / `cli()`), capped in
  depth, entries, string length and total values. `debug()` is rebuilt on it: values are now
  escaped, a third `$limits` argument overrides the caps, and the rendering follows the context
  (HTML block on a page, ANSI text in a terminal; `Dumper::forceMode()` to impose one).
  `dump()` and `dd()` go through `debug()`.
- Warnings, notices and deprecations are no longer silent (`ExceptionHandler::handleError()`):
  logged once per request, and displayed in place as a `.debug-message.debug-error` block in debug
  mode (not for a JSON request).
- CLI: `CliApplication` registers the `ExceptionHandler` in terminal mode. Uncaught exceptions,
  fatal errors and warnings of a `craft` command are logged (with the command line) and displayed
  in colour. `ExceptionHandler::register()` installs the three handlers, for web and CLI.
- `MasterCollection::paginate()`: optional `projection` key in `$paginateInfo`, to leave a heavy
  field out of a list.
- `SubmitField` (`submit` field type): the submit button is a field like the others, placed with
  `renderField('submit')` in a custom layout. It has no `name` (nothing sent in the POST) and
  `FormBuilder::only()` leaves it out. `submit()` becomes a shortcut for it.
- `HtmlView::addProperty()` (`<meta property>`, Open Graph; can repeat, e.g. `article:tag`),
  `HtmlView::addLink()` (`<link rel>`: canonical, alternate…), and a `head` block appended at the
  end of `getHeader()`.

### Changed

- Debug error page: each stack trace frame with arguments is a collapsible `<details>`.
- The error layout declares `charset` and `viewport`.
- Docs: the chapters are renumbered (new chapter 19 Events; Mails/HTTP client/Tests become 20/21/22).

### Fixed

- Debug error page: the arguments of the stack trace were dumped in full, which could exhaust
  memory on a big object graph. They're now dumped with tight limits, and the exception message
  is escaped. A project that published `view/error/debug.php` should publish it again.


## [0.2.2] - 2026-10-25

### Upgrading

- `Queue::PENDING`/`PROCESSING`/`SUCCESS`/`FAILED` are replaced by the `JobStatus` enum
  (a breaking change, although shipped in a patch release).
- Run `php craft db-sync` to create the new index on `jobs`.

### Added

- `embeddedIn()`: `'many' => true` for a field holding an array of snapshots (e.g. a post's
  tags). Only the matching items are synced (`arrayFilters`), `unset` pulls the item out.
- `MasterCollection::updateMany()` takes driver options as an optional third argument.
- `MongoClient::_transaction()`, off by default (`db.MongoDB.transaction`, needs a replica set).
  When on, a write and its snapshot propagation are done in one transaction.
- `php craft db-replica`: initializes a single-node replica set.

### Fixed

- Queue: two workers could run the same job (claiming is now atomic), and a job stayed
  `processing` forever when its worker died (lease, see `MasterJob::timeout()`).


## [0.2.1] - 2026-10-15

### Changed

- `mongodb/mongodb` is required in `^2.4.1` (security fix of the library), so the `mongodb`
  extension must be 2.4.1 or later.
- Docs: the installation chapter lists the download links, the usual traps (three "MongoDB"s,
  the extension on Windows, the right `php.ini`) and the commands to check everything is ready.
  Node.js is now optional: the starter ships with its assets compiled.


## [0.2.0] - 2026-10-10

### Upgrading from 0.1

- **`ext-intl` is now required** (`composer.json`).
- **Startup moved into the framework.** `public/index.php` and `craft` must call
  `\SkankyDev\Core\Bootstrap::boot()` right after the autoload, before `config/bootstrap.php`.
  Loading the `.env` and setting the timezone are done by the framework: remove that code from
  your `config/bootstrap.php`, which is now for your application only.
- **The default language is English** (`i18n.locale` = `en_US`). Validation, upload, middleware
  and `php craft` messages are now translated. To keep them in French, add to `master.config.php`:
  `'i18n' => ['locale' => 'fr_FR', 'fallback' => 'fr', 'available' => ['fr_FR']]`.
- **`Request::file()` returns `UploadedFile` objects** (or a list of them for a `name[]` field)
  instead of raw `$_FILES` arrays, and `null` for a field left empty.
- **CLI options lose their dashes**: `--force` and `-m` arrive as `$arg['force']` and `$arg['m']`.
  Commands reading `$arg['--force']` must be updated.
- **Exception messages are in English.** Code matching on their French text must be updated.
- `UploadedFile::ERROR_MESSAGES` is replaced by `UploadedFile::ERROR_KEYS` (translation keys).
- If you published the framework translations (`php craft publish -p=lang`), publish them again:
  a published `skankydev.php` replaces the framework's whole file and misses the new keys.

### Added

- **Internationalization** with ext/intl (ICU MessageFormat: plurals, select, locale-aware numbers):
  `__()` helper, `Translator` (domain files in `src_front/lang/{language}/{domain}.php`,
  fallback chain `fr_CA` → `fr` → `i18n.fallback` → raw key), `LocaleNegotiator` (`Accept-Language`).
- `TranslatorHelper` view helpers: `htmlLang()`, `number()`, `currency()`, `date()`.
- Framework messages (validation, upload, middlewares, `php craft`) in English and French, in the
  reserved `skankydev` domain, publishable with `php craft publish -p=lang`.
- `php craft lang-sync`: adds the translation keys used in the code to the language files
  (`--dry-run`, `--prune`, `--check`).
- File uploads: `UploadedFile` (real MIME type from the content, safe extension, `store()`),
  `StoredFile` embedded document (URL computed from the config, never stored), `upload` config,
  `file` form field with automatic `multipart/form-data`.
- Validation rules `file`, `image`, `mimes` and `max_size`.
- `crud-maker -m=<Module>`: generates the CRUD inside another module.
- Declared routes accept a fully qualified controller class (`DashboardController::class`),
  the module is deduced from it.
- `StringFacility::slugify()`, with transliteration.
- `Bootstrap` class, `LANG_FOLDER` constant, `i18n` config block.
- English documentation in `docs/en/` (French moved to `docs/fr/`), `README.fr.md`.
- GitHub Actions workflow running the test suite on PHP 8.4 and 8.5.

### Changed

- Exception messages, logs and error pages are in English, hardcoded (never translated, so a
  broken language file can't break the error page).
- CrudMaker templates generate English code, and dates through `$this->date()`.
- Form field and submit templates are rendered in an isolated scope.

### Removed

- The unused `fields/password.php` publishable template (project-specific: strength meter, show toggle).

### Fixed

- Convention URLs dropped the module prefix when linking from inside the same module
  (`/admin/post` linked to `/post/create`).
- The delete button of `part.table` pointed to the `edit` action.
- `MasterCommand::getInfo()` used an undefined variable in its error message.

[Unreleased]: https://github.com/skankydev/framework/compare/0.3.2...HEAD
[0.3.2]: https://github.com/skankydev/framework/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/skankydev/framework/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/skankydev/framework/compare/0.2.2...0.3.0
[0.2.2]: https://github.com/skankydev/framework/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/skankydev/framework/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/skankydev/framework/compare/0.1.1...0.2.0
