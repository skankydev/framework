# 16 Craft and CLI (CrudMaker + Publishable)

## The entry point

Everything goes through `php craft <signature> [options]`. It's a single file at the project root, which starts `CliApplication` with `$argv`:

```php
require(__DIR__ . '/vendor/autoload.php');
\SkankyDev\Core\Bootstrap::boot();
require(__DIR__ . '/config/bootstrap.php');

use SkankyDev\Cli\CliApplication;
$app = new CliApplication($argv);
```

The same startup as `public/index.php`: `Bootstrap::boot()` for the framework (the `.env`, the config, the timezone), then `config/bootstrap.php` for your application.

`CliApplication` initializes the config (like on the web side), reads the arguments, then finds and runs the requested command. `php craft` on its own (or `help`) displays the list of available commands with their description.

### Arguments

A simple convention, with no dependency on an external parser:

- the first argument without `-` is the command (`php craft queue-worker` → `queue-worker`);
- `--key=value` or `--key value` gives `['key' => 'value']`;
- `--flag` on its own gives `['flag' => true]`;
- `-h` is an alias for `help`;
- a value without a key is indexed numerically.

A command that accepts a short form and a long form simply reads both keys: `$arg['publish'] ?? $arg['p']`.

## Creating a command

You create a class in your module's `Command/` folder, which extends `MasterCommand`, with a signature and a help text:

```php
class Cleanup extends MasterCommand {
    static protected string $signature = 'cleanup';
    static protected string $help = 'Cleans up the temporary files';

    public function run(array $arg = []): void {
        $this->info('Cleaning up...');
        // ...
        $this->success('Done!');
    }
}
```

`$arg` contains the parsed options, without the `command` key (already consumed by `CliApplication`).

`$help` can also be a translation key (`'app.cli.cleanup.help'`, see [21 Internationalization](021-I18n.md)): if the key exists, `php craft` displays its translation, otherwise it displays the text as is. The framework's commands work that way, and speak the language of `i18n.locale` (in the CLI, there's no browser to ask for another one).

You have nothing to register: `CliApplication::autoRegister()` scans the `Command/` folder of each module declared in the config, plus SkankyDev's own. A valid class in the right folder is enough to show up in `php craft help`.

### Output helpers

`MasterCommand` gives you what you need to talk to the user (it's the `CliMessage` trait):

```php
$this->info('...');     // cyan
$this->success('...');  // green
$this->warning('...');  // yellow
$this->error('...');    // red
$this->text('...');     // plain

$this->ask('Your name');         // asks a question, returns the input
$this->valide('Confirm');        // confirmation (y/n)
$this->choice(['a' => 'Option A', 'b' => 'Option B']); // numbered menu
```

## The shipped commands

- **`crud-maker`**: interactively generates a Document, its Collection, its Controller, a Form and the 4 CRUD views (index, create, edit, show) from templates (see the Publishable below). With `-m=<Module>` (or `--module=<Module>`), everything is generated in that module: the classes in `src/{Module}/` (namespace `{Module}\…`), the views in `view/{module}/{document}/`. The module must be declared in the `Module` config. Without the option, it's the default module (`App`), with the views directly in `view/{document}/`.
  ```bash
  php craft crud-maker Article            # App
  php craft crud-maker Article -m=Admin   # Admin
  ```
  If you published the templates (`php craft publish -p=template`), your copy must use `$module` and `$viewPath` instead of `App` and `$dashed` in the view names, otherwise it will always generate into `App`.
- **`db-sync`**: syncs the declared Mongo indexes (each Collection's `getIndexes()`, see [09 Model](009-Model.md)).
- **`queue-worker`**: runs the worker that processes pending jobs (see [17 Queue and Jobs](017-Queue-and-Jobs.md)).
- **`publish`**: copies the framework's default resources into your project (detailed just below).

## The Publishable pattern

The framework's `Publishable/` folder (`vendor/skankydev/framework/src/Publishable/`) holds the framework's generic resources that have a default value, but that a project may want to customize: the error views ([14](014-Error-Handling.md)), the CrudMaker templates, a few utility parts (`table`, `paginator`, see [10 View](010-View.md)), the `FormBuilder` field templates ([12.1](012.1-Fields.md)), the framework's translations ([21](021-I18n.md)).

As long as you don't touch them, almost everything works with the framework's defaults: the related config keys (`view.error`, `template.folder`, `view.fields`...) point directly into `Publishable/`. **The parts (`table`, `paginator`) are the exception**: they have no config key, `part()` only looks in your views folder ([10 View](010-View.md)), so they have to be published to work (the starter already has them). The translations (`lang`) have no key either, but for the opposite reason: the `Translator` first reads the `skankydev.php` in your language folder and falls back on the framework's on its own, so once published, there's nothing to configure. The day you want to customize one of these pieces, or to get the parts, `php craft publish` copies it into your own project:

```bash
php craft publish -p=error      # a specific resource
php craft publish               # interactive menu
```

The command then displays the piece of config to check or add in `config/master.config.php` to point to your copy. It **never modifies that file on its own**, I found it too risky to break its structure with automatic edits. And if a destination file already exists, it asks for confirmation before overwriting it.

The goal: avoid having generic, never-customized files lying around in every new project. Publishing is an explicit choice, not a starting point.
