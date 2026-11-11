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
- [09 Responses](009-Responses.md): `view()`, `redirect()`, `response()` and JSON/HTML negotiation.

## Data

- [10 Model](010-Model.md): the Document, the Collection, CRUD and dirty tracking.
  - [10.1 The Persistable](010.1-Persistable.md): EmbeddedDocument and EmbeddedSnapshot.
  - [10.2 Behaviors](010.2-Behaviors.md): hooks you switch on with a trait.
  - [10.3 Relations and propagation](010.3-Relations.md): `belongsTo`, `hasMany`, and syncing snapshots.

## Display

- [11 View](011-View.md): views, layouts, blocks and Parts.
- [12 Internationalization](012-I18n.md): `__()`, language files, ICU, choosing the language.

## Forms and security

- [13 Forms](013-Forms.md): the FormBuilder.
  - [13.1 Fields](013.1-Fields.md): the existing fields and how to add one.
  - [13.2 Validation](013.2-Validation.md): the existing rules and how to create one.
  - [13.3 CSRF](013.3-CSRF.md): the token, forms and AJAX.
- [14 Auth](014-Auth.md): login, logout, keepers, gates and providers.

## When things go wrong

- [15 Error handling](015-Error-Handling.md): the global handler, debug, 404s.
- [16 Logs](016-Logs.md): what gets written, where, and cleaning up.

## Outside the web page

- [17 Craft and CLI](017-Craft-and-CLI.md): `php craft`, the CrudMaker, the Publishable, creating a command.
- [18 Queue and Jobs](018-Queue-and-Jobs.md): jobs, the worker, Supervisor.
- [19 Mails](019-Mails.md): the mail object, the sender, sending through the queue.
- [20 The outgoing HTTP client](020-Outgoing-HTTP-Client.md): calling an external API.

## Under the hood

- [21 Tests](021-Tests.md): why only the framework is covered, and how to run them.
