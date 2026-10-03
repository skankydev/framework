# 10 View

## C'est des vues

Des templates PHP tout bêtes, rangés dans `src_front/view/` et rendus par `HtmlView`. Tu n'y touches jamais directement : tu appelles `view('dossier.fichier', $data)` (un helper global) et ça te renvoie une `Response` prête à partir ([11 Les Réponses](011-Les-Reponses.md)).

Dans le template, `$this` est l'instance de `HtmlView`. Toutes les variables de `$data` sont extraites, donc tu les utilises directement par leur nom, et `$this->xxx()` te donne les helpers.

```php
// controller
return view('post.show', ['post' => $post]);

// src_front/view/post/show.php
<h1><?= e($post->title) ?></h1>
```

Le nom de la vue est en dot-notation, calqué sur le système de fichiers : `post.show` → `src_front/view/post/show.php`.

## Les layouts

Une vue est rendue seule, puis insérée dans un layout, où elle devient disponible sous le nom `content`. Le layout par défaut est `layout.default` (`src_front/view/layout/default.php`). Il est piloté par la config `view.layout`, donc tu peux le changer projet par projet, comme `view.error` ou `view.fields`.

Dans le layout, tu affiches la vue avec `$this->fetch('content')`.

Pour changer de layout sur une page précise, ça se passe depuis la vue elle-même, tout en haut du template (le controller n'a jamais la main sur l'instance `HtmlView`, `view()` renvoie directement une `Response`) :

```php
$this->setLayout('layout.error');   // un autre layout
$this->setLayout(null);             // pas de layout du tout, pratique pour un fragment AJAX
```

### Le `<head>`, rempli depuis la vue

Une vue peut ajouter des metas ou des assets qui remontent dans le `<head>` du layout, sans que le layout ait besoin de savoir ce que chaque page utilise :

```php
$this->setTitle('Mon article');
$this->addMeta('description', '...');
$this->addCss('/dist/post.css');
$this->addJs('/dist/post.js');
```

Côté layout, tout ressort d'un coup avec `<?= $this->getHeader() ?>` (metas + css + js accumulés).

Même principe pour un `<script>` inline : tu le captures depuis ta vue au lieu de l'écrire dans le layout.

```php
<?php $this->startScript(); ?>
<script>console.log('page chargée');</script>
<?php $this->stopScript(); ?>
```

Et `$this->getScript()` (en général juste avant `</body>`) sort tout ce qui a été accumulé, depuis une ou plusieurs vues.

### Les blocks : capturer un bout de vue pour le ressortir ailleurs

C'est la même idée que le script, mais généralisée, avec un nom :

```php
<?php $this->startBlock('sidebar'); ?>
<div>Widget spécifique à cette page</div>
<?php $this->stopBlock(); ?>
```

Et là où tu veux l'afficher (le layout, une autre vue) :

```php
<?= $this->getBlock('sidebar') ?>
```

Deux choses à retenir : le nom se donne à `startBlock()`, pas à `stopBlock()` (tu sais ce que tu captures dès la première ligne), et deux blocks du même nom **s'accumulent** au lieu de s'écraser. Les blocks peuvent aussi s'imbriquer sans souci, chacun capture bien ce qui lui appartient.

### Fil d'Ariane

```php
$this->addCrumb('Documentation', ['name' => 'doc-index'], 'icon-pen-tool');
```

Le deuxième argument passe par l'`UrlBuilder` si c'est un tableau, ou reste tel quel si c'est déjà une URL en string. L'affichage se fait ensuite avec `part.breadcrumb` (voir les Parts plus bas).

## Les helpers dispo dans une vue

En plus de ceux qu'on a déjà croisés (`e()`, `url()`, `$this->url()`, `csrf_field()`, `old()`, `error()`, `flash()`...), il y a deux petits utilitaires HTML :

```php
$this->link('Voir le profil', ['name' => 'user-show', 'params' => [$id]], ['class' => 'btn']);
// <a href="/user/show/..." class="btn">Voir le profil</a>

$this->surround('Nouveau', 'span', ['class' => 'badge']);
// <span class="badge">Nouveau</span>
```

## Les Parts

Des bouts de vue réutilisables : un header, une pagination, un tableau générique. C'est l'équivalent des *View Cells* de CakePHP ou des *View Composers* de Laravel. C'est cool, on les appelle depuis n'importe quelle vue ou layout :

```php
<?= $this->part('part.header') ?>
<?= $this->part('part.table', ['items' => $users, 'display' => $display]) ?>
```

Le nom suit la même dot-notation que `view()`, dans le même dossier de vues : `part.header` → `src_front/view/part/header.php`.

Deux parts sont livrés par le framework (SkankyDev) : `part.table` (un tableau générique, piloté par `getDisplayField()` d'une Collection, voir [09 Model](009-Model.md)) et `part.paginator`. Les autres parts que tu verras dans le projet (`part.breadcrumb`, `part.header`...) sont côté `App`, pas fournis par défaut.

### C'est encore plus cool, on peut lier des bouts de code

Un part peut avoir une classe compagnon qui lui prépare des variables. Elle est trouvée par convention de nom, sous le **module courant** : `part.auth` → `{ModuleCourant}\View\Part\AuthPart`. Elle étend `MasterPart` et n'a qu'une méthode à écrire :

```php
class AuthPart extends MasterPart {
    public function data(array $options): array {
        return ['user' => Auth::user()];
    }
}
```

Les données que renvoie `data()` sont fusionnées avec les options passées à l'appel, puis le template est rendu. Cette classe est facultative : sans elle, le part est juste un template classique. Tu t'en sers seulement quand tu as besoin de calculer quelque chose avant l'affichage. Et comme la classe est construite par le `MasterFactory`, elle profite de l'injection de dépendance ([08 Controller](008-Controller.md)).

Si le part que tu appelles ne vit pas dans le module courant (un part partagé, ou un module tiers), la convention n'ira pas le chercher au bon endroit. Dans ce cas, tu forces la classe dans la config `class.parts`, avec le nom complet donné à `part()` comme clé :

```php
'class' => [
    'parts' => [
        'post.part.content' => \Pomme\View\Part\PommePart::class,
    ],
],
```

Cette entrée, si elle existe, gagne toujours sur la convention.
