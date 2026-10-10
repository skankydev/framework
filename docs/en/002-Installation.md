# 02 Installation

Install it, start it, and you've got a CRUD running before your coffee's done. Promise, I tested it end to end.

## What you need

- **PHP 8.4 or later**, with the `mongodb` and `intl` extensions (for translations, see [21](021-I18n.md)). Get a recent version of the extension (2.4.1 or later): older ones prevent Composer from installing the fixed version of the `mongodb/mongodb` library (a security flaw was fixed in 2.4.1). To check your version: `php --ri mongodb`.
- **Composer**.
- **A running MongoDB** (locally is more than enough).
- **Node.js and npm**, to compile the CSS and JS with Vite.
- A web server: Apache with `mod_rewrite`, or PHP's built-in server to try it out.

## Create the project

The starting project is called the **starter**. You grab it with Composer:

```bash
composer create-project skankydev/starter my-project
cd my-project
npm install
npm run build
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

While you're tweaking the CSS or JS, keep this running in another terminal:

```bash
npm run dev
```

It recompiles on every change in `src_front/`.

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
- **The site shows up without any style**: you forgot `npm run build`.
- **404 everywhere except the home page** (with Apache): `mod_rewrite` isn't enabled, or `AllowOverride` is set to `None`.
