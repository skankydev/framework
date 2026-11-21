# 22 Les tests

## Le périmètre : SkankyDev, pas l'App

Les tests vivent dans le dépôt du framework (`skankydev/framework`), pas dans ton projet. La suite protège le **cœur réutilisable**, le dossier `src/` du framework, pas le code métier d'un projet donné. C'est écrit noir sur blanc dans son `phpunit.xml` :

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

Mon raisonnement : le framework est censé rester stable et être réutilisé d'un projet à l'autre, donc c'est lui qui mérite une vraie couverture. Le code applicatif (`src/App`) change à chaque projet et a ses propres besoins. Ça veut pas dire « pas de tests côté App », juste que c'est pas le job de cette suite-là.

## Deux suites

```xml
<testsuite name="SkankyDev">
    <directory>tests/SkankyTest/TestCase</directory>
</testsuite>
<testsuite name="Integration">
    <directory>tests/SkankyTest/Integration</directory>
</testsuite>
```

- **`TestCase/`** : les tests unitaires, sans aucune dépendance externe (pas de Mongo, pas de filesystem au-delà du strict nécessaire). C'est le gros de la suite.
- **`Integration/`** : il leur faut une vraie instance MongoDB qui tourne (les Collections, le CRUD réel, la propagation des snapshots, voir [10.3](010.3-Relations.md)).

## `tests/bootstrap.php`

Les tests chargent pas la vraie config du projet. Le `bootstrap.php` monte à la main une config minimale (**pas** `Config::initConf()`), juste ce dont les classes testées ont besoin :

```php
Config::$conf = [];
Config::set('default.namespace', 'App');
Config::set('view.folder', __DIR__ . '/App/View');
Config::set('class.fields', [...]);
// ...
```

Un truc à garder en tête : si tu ajoutes une clé de config dont une classe du framework a besoin (comme `view.layout` ou `Module`, ajoutées en cours de route), pense à la rajouter aussi dans ce bootstrap. Sinon les tests qui passent par cette classe plantent avec un `null` sorti de nulle part, sans lien apparent avec ce que tu viens de changer.

## Les commandes

```bash
composer test              # phpunit, toute la suite
composer coverage          # phpunit --coverage-text
composer coverage-pretty   # phpunit --coverage-html build/coverage
```

Pour viser plus précis :

```bash
php vendor/bin/phpunit tests/SkankyTest/TestCase/Http/UrlBuilderTest.php  # un fichier
php vendor/bin/phpunit --filter testBuildFromNamedRoute                   # un test
```

## La routine

Après toute modification dans `src/` du framework : `composer test`, jamais `php -l`. `php -l` vérifie juste la syntaxe d'un fichier isolé. La suite, elle, attrape à la fois la syntaxe (elle plante direct si un fichier ne se parse pas) et les régressions de comportement, ce qu'un simple lint verra jamais.
