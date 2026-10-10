# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/). While in `0.x`, a minor
version (`0.1` → `0.2`) may contain breaking changes: they're listed under **Upgrading**.

## [Unreleased]

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

[Unreleased]: https://github.com/skankydev/framework/compare/0.2.0...HEAD
[0.2.0]: https://github.com/skankydev/framework/compare/0.1.1...0.2.0
