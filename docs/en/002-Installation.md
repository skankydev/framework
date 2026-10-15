# 02 Installation

Install it, start it, and you've got a CRUD running before your coffee's done. Promise, I tested it end to end.

## What you need

- **PHP 8.4 or later** ([php.net/downloads](https://www.php.net/downloads), or [windows.php.net](https://windows.php.net/download/) on Windows), with two extensions:
  - `mongodb`, version 2.4.1 or later ([installation on php.net](https://www.php.net/manual/en/mongodb.installation.php));
  - `intl`, for translations ([21 I18n](021-I18n.md)). It ships with PHP, you often just have to enable it in `php.ini`.
- **Composer** ([getcomposer.org/download](https://getcomposer.org/download/)).
- **A running MongoDB**: the [Community Server](https://www.mongodb.com/try/download/community) running locally is more than enough. [Compass](https://www.mongodb.com/products/tools/compass) is handy to see what's in your database.
- A web server: Apache with `mod_rewrite`, or PHP's built-in server to try it out.
- *Bonus*: **Node.js and npm** ([nodejs.org](https://nodejs.org/)). The starter ships with its CSS and JS already compiled, you only need them to change `src_front/` and recompile with Vite.

### The usual traps

- **Three different "MongoDB"s.** The MongoDB server (the database), the `mongodb` PHP extension (the driver, written in C) and the `mongodb/mongodb` library (written in PHP). You need all three, and Composer only takes care of the last one.
- **The extension on Windows.** It comes as a DLL from the driver's [releases page](https://github.com/mongodb/mongo-php-driver/releases). Pick the one that matches your PHP exactly: same version (8.4), same architecture (x64), and TS or NTS depending on your build (`php -i` tells you, "Thread Safety" line). Copy `php_mongodb.dll` into PHP's `ext/` folder, then add `extension=mongodb` to `php.ini`.
- **The right `php.ini`.** `php --ini` tells you which one is loaded. Careful, command-line PHP and Apache's PHP don't always use the same one: if it works in the terminal but not in the browser, that's often why.
- **On Linux and Mac**, use [PIE](https://github.com/php/pie), which replaces PECL: `pie install mongodb/mongodb-extension`. Your distribution's package works too, if it's recent enough.

### Check that everything is ready

Before creating the project, each command should answer:

```bash
php -v                 # 8.4 or later
php -m                 # must list mongodb and intl
php --ri mongodb       # extension version, 2.4.1 or later
composer --version
```

For MongoDB, connect to `mongodb://localhost:27017` with Compass, or run `mongosh` if it's installed (it ships separately from the server).

## Create the project

The starting project is called the **starter**. You grab it with Composer:

```bash
composer create-project skankydev/starter my-project
cd my-project
```

And if you installed Node.js, to be able to work on the front end:

```bash
npm install
```

The framework itself (`skankydev/framework`) lands in `vendor/` on its own, like any other dependency. You don't see it in your project, and that's on purpose: your folder only contains *your* code. To update it later:

```bash
composer update skankydev/framework
```

## Set up the .env

`composer create-project` copied `.env.dist` to `.env` for you. Open it and set at least your database:

```ini
APP_DEBUG=1

DB_MONGO_HOST=localhost
DB_MONGO_PORT=27017
DB_MONGO_DATABASE=MyProject
```

`APP_DEBUG=1` gives you detailed error pages, perfect in dev. In production, set it to `0` ([14 Error handling](014-Error-Handling.md)). The `.env` is never committed: that's where your secrets live.

## Start the site

To try it out, PHP's built-in server is enough:

```bash
php -S localhost:8000 -t public
```

Open `http://localhost:8000`: you should land on the starter's home page. If you see "It's running. Well done.", you've made it.

With Apache, point the document root to the `public/` folder (the `.htaccess` at the root redirects to `public/` if you can't). The only folder exposed to the world is `public/`.

While you're tweaking the CSS or JS (Node.js required), keep this running in another terminal:

```bash
npm run dev
```

It recompiles on every change in `src_front/`. When you're done, `npm run build` produces the final version in `public/dist/`.

## A CRUD in 10 minutes

This is where `php craft` comes in ([16 Craft and CLI](016-Craft-and-CLI.md)). You give it your document's name, it asks you a few questions about the fields, and it generates everything else:

```bash
php craft crud-maker Article
```

For each field, it asks: its name, its type (you type the menu number: `0` for `string`, `1` for `int`, etc.), and whether it's required. To finish, leave the field name empty.

```
Field name ? title
0 : string   1 : int   2 : float   3 : bool ...
Field type ?  0
Required ? (y/n) y

Field name ? body
Field type ?  0
Required ? (y/n) n

Field name ?            <- empty, we're done
```

And it writes eight files:

| File | Role |
|---|---|
| `src/App/Model/Document/Article.php` | the Document: the value ([09 Model](009-Model.md)) |
| `src/App/Model/ArticleCollection.php` | the Collection: talks to MongoDB |
| `src/App/Controller/ArticleController.php` | `index`, `create`, `store`, `show`, `edit`, `update`, `delete` |
| `src/App/Form/ArticleForm.php` | the form, with its validation rules ([12 Forms](012-Forms.md)) |
| `src_front/view/article/{index,create,edit,show}.php` | the four views |

No route to write: convention-based routing takes care of everything ([06 Routing](006-Routing.md)). Go to `http://localhost:8000/article` and you get:

- the paginated list of articles;
- the creation form (`/article/create`), with CSRF protection already wired in ([12.3 CSRF](012.3-CSRF.md));
- the show page, editing (with the form pre-filled) and deletion;
- validation: if you leave `title` empty, you're sent back to the form with the error and what you had typed.

All this code is yours. It's there to save you from the blank page, not to lock you in: change the views, add fields, rename whatever you want.

If your Collection declares Mongo indexes (`getIndexes()`), the `php craft db-sync` command creates them.

## And if it doesn't work

- **Blank page or connection error**: check that MongoDB is running, and the `DB_MONGO_*` values in the `.env`.
- **`composer create-project` refuses to install `mongodb/mongodb`**: the `mongodb` extension is missing or too old. Check with `php --ri mongodb`.
- **The site shows up without any style**: `public/dist/` is empty or missing. Run `npm run build` again.
- **404 everywhere except the home page** (with Apache): `mod_rewrite` isn't enabled, or `AllowOverride` is set to `None`.
