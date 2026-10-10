# SkankyDev, the docs

**English** · [Français](../fr/000-table_des_matiere.md)

A small homemade PHP MVC framework, built to do CRUD on MongoDB without the headache. Here's the plan, in the order you discover things.

## Getting started

- [01 Philosophy](001-Philosophy.md): where it comes from, where it's going, and why wet kisses are the best.
- [02 Installation](002-Installation.md): install the starter and get a CRUD running.
- [03 Introduction](003-Introduction.md): the journey of a request, the folder structure, modules.

## The request, from start to finish

- [04 Config](004-Config.md): merging the files, class aliases, how to get a value.
- [05 The Client Request](005-The-Client-Request.md): request info, the Session, inputs and flash messages.
- [06 Routing](006-Routing.md): the convention and declared routes.
  - [06.1 The UrlBuilder](006.1-UrlBuilder.md): build a URL without hardcoding it.
- [07 Middlewares](007-Middlewares.md): how they work, the ones that exist, adding one.
- [08 Controller](008-Controller.md): dependency injection and middlewares.
- [11 Responses](011-Responses.md): `view()`, `redirect()`, `response()` and JSON/HTML negotiation.

## Data

- [09 Model](009-Model.md): the Document, the Collection, CRUD and dirty tracking.
  - [09.1 The Persistable](009.1-Persistable.md): EmbeddedDocument and EmbeddedSnapshot.
  - [09.2 Behaviors](009.2-Behaviors.md): hooks you switch on with a trait.
  - [09.3 Relations and propagation](009.3-Relations.md): `belongsTo`, `hasMany`, and syncing snapshots.

## Display

- [10 View](010-View.md): views, layouts, blocks and Parts.
- [21 Internationalization](021-I18n.md): `__()`, language files, ICU, choosing the language.

## Forms and security

- [12 Forms](012-Forms.md): the FormBuilder.
  - [12.1 Fields](012.1-Fields.md): the existing fields and how to add one.
  - [12.2 Validation](012.2-Validation.md): the existing rules and how to create one.
  - [12.3 CSRF](012.3-CSRF.md): the token, forms and AJAX.
- [13 Auth](013-Auth.md): login, logout, keepers, gates and providers.

## When things go wrong

- [14 Error handling](014-Error-Handling.md): the global handler, debug, 404s.
- [15 Logs](015-Logs.md): what gets written, where, and cleaning up.

## Outside the web page

- [16 Craft and CLI](016-Craft-and-CLI.md): `php craft`, the CrudMaker, the Publishable, creating a command.
- [17 Queue and Jobs](017-Queue-and-Jobs.md): jobs, the worker, Supervisor.
- [18 Mails](018-Mails.md): the mail object, the sender, sending through the queue.
- [19 The outgoing HTTP client](019-Outgoing-HTTP-Client.md): calling an external API.

## Under the hood

- [20 Tests](020-Tests.md): why only the framework is covered, and how to run them.
