# 10 View

## They're views

Plain old PHP templates, stored in `src_front/view/` and rendered by `HtmlView`. You never touch it directly: you call `view('folder.file', $data)` (a global helper) and it gives you back a `Response` ready to go ([11 Responses](011-Responses.md)).

In the template, `$this` is the `HtmlView` instance. All the variables in `$data` are extracted, so you use them directly by name, and `$this->xxx()` gives you the helpers.

```php
// controller
return view('post.show', ['post' => $post]);

// src_front/view/post/show.php
<h1><?= e($post->title) ?></h1>
```

The view name uses dot notation, mirroring the file system: `post.show` → `src_front/view/post/show.php`.

## Layouts

A view is rendered on its own, then inserted into a layout, where it becomes available under the name `content`. The default layout is `layout.default` (`src_front/view/layout/default.php`). It's driven by the `view.layout` config, so you can change it project by project, like `view.error` or `view.fields`.

In the layout, you display the view with `$this->fetch('content')`.

To change the layout on a specific page, it happens from the view itself, at the very top of the template (the controller never gets its hands on the `HtmlView` instance, `view()` returns a `Response` directly):

```php
$this->setLayout('layout.error');   // another layout
$this->setLayout(null);             // no layout at all, handy for an AJAX fragment
```

### The `<head>`, filled from the view

A view can add metas or assets that go up into the layout's `<head>`, without the layout needing to know what each page uses:

```php
$this->setTitle('My article');
$this->addMeta('description', '...');
$this->addCss('/dist/post.css');
$this->addJs('/dist/post.js');
```

On the layout side, everything comes out at once with `<?= $this->getHeader() ?>` (accumulated metas + css + js).

Same idea for an inline `<script>`: you capture it from your view instead of writing it in the layout.

```php
<?php $this->startScript(); ?>
<script>console.log('page loaded');</script>
<?php $this->stopScript(); ?>
```

And `$this->getScript()` (usually just before `</body>`) outputs everything that's been accumulated, from one or several views.

### Blocks: capture a piece of a view to output it elsewhere

It's the same idea as the script, but generalized, with a name:

```php
<?php $this->startBlock('sidebar'); ?>
<div>Widget specific to this page</div>
<?php $this->stopBlock(); ?>
```

And wherever you want to display it (the layout, another view):

```php
<?= $this->getBlock('sidebar') ?>
```

Two things to remember: the name is given to `startBlock()`, not to `stopBlock()` (you know what you're capturing from the first line), and two blocks with the same name **accumulate** instead of overwriting each other. Blocks can also be nested without a problem, each one captures what belongs to it.

### Breadcrumb

```php
$this->addCrumb('Documentation', ['name' => 'doc-index'], 'icon-pen-tool');
```

The second argument goes through the `UrlBuilder` if it's an array, or stays as is if it's already a string URL. It's then displayed with `part.breadcrumb` (see Parts below).

## The helpers available in a view

On top of the ones we've already come across (`e()`, `url()`, `$this->url()`, `csrf_field()`, `old()`, `error()`, `flash()`...), there are two small HTML utilities:

```php
$this->link('See the profile', ['name' => 'user-show', 'params' => [$id]], ['class' => 'btn']);
// <a href="/user/show/..." class="btn">See the profile</a>

$this->surround('New', 'span', ['class' => 'badge']);
// <span class="badge">New</span>
```

And the ones that depend on the language (`__()`, `$this->htmlLang()`, `$this->number()`, `$this->date()`...) are in [21 Internationalization](021-I18n.md).

## Parts

Reusable pieces of views: a header, a pagination, a generic table. It's the equivalent of CakePHP's *View Cells* or Laravel's *View Composers*. It's cool, you call them from any view or layout:

```php
<?= $this->part('part.header') ?>
<?= $this->part('part.table', ['items' => $users, 'display' => $display]) ?>
```

The name follows the same dot notation as `view()`, in the same views folder: `part.header` → `src_front/view/part/header.php`.

Two parts are provided by the framework (SkankyDev): `part.table` (a generic table, driven by a Collection's `getDisplayField()`, see [09 Model](009-Model.md)) and `part.paginator`. But careful: `part()` only looks in **your** views folder, so they only work once **published** into your project (the starter already has them). If you see "the file … part/table.php does not exist":

```bash
php craft publish -p=part
```

([16 Craft and CLI](016-Craft-and-CLI.md) goes into the Publishable.) The other parts you'll see in a project (`part.breadcrumb`, `part.header`...) are on the `App` side, not provided by default.

### Even cooler, you can attach some code

A part can have a companion class that prepares variables for it. It's found by naming convention, under the **current module**: `part.auth` → `{CurrentModule}\View\Part\AuthPart`. It extends `MasterPart` and has only one method to write:

```php
class AuthPart extends MasterPart {
    public function data(array $options): array {
        return ['user' => Auth::user()];
    }
}
```

The data returned by `data()` is merged with the options passed in the call, then the template is rendered. This class is optional: without it, the part is just a regular template. You only use it when you need to compute something before displaying. And since the class is built by the `MasterFactory`, it benefits from dependency injection ([08 Controller](008-Controller.md)).

If the part you're calling doesn't live in the current module (a shared part, or a third-party module), the convention won't look for it in the right place. In that case, you force the class in the `class.parts` config, with the full name given to `part()` as the key:

```php
'class' => [
    'parts' => [
        'post.part.content' => \Pomme\View\Part\PommePart::class,
    ],
],
```

That entry, if it exists, always wins over the convention.
