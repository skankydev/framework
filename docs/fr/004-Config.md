# 04 Config

## Le .env

Il existe un fichier `.env.dist` dans le projet. Il faut le copier et le renommer en `.env` pour avoir les configurations secrètes, qui dépendent de l'environnement.

## Comment retrouver une valeur

La classe `Config` est une classe statique. Elle récupère les différents tableaux de configuration : `master.config.php`, qu'on trouve dans le dossier `config` à la racine du projet, et ceux des différents modules, dans `{Module}/Config/config.php`.

Elle utilise le trait `ArrayPathable`, qui simplifie l'utilisation des gros tableaux : tu accèdes à une valeur avec un chemin à points.

```php
use SkankyDev\Config\Config;

$value = Config::get('path.to.value');

Config::set('path.to.value', $value);
```

## Les différents fichiers

La config est construite par `Config::initConf()`, appelé une seule fois au démarrage (par `Application` ou `CliApplication`). Elle fusionne trois sources, et le dernier écrase le précédent :

1. `default.config.php`, dans le framework (`vendor/skankydev/framework/src/Config/`) : les défauts du framework ;
2. `src/{Module}/Config/config.php` : la config de chaque module déclaré dans `Module` (pour l'instant, juste `App`) ;
3. `config/master.config.php` : la config du projet, qui a toujours le dernier mot.

En clair : tu n'as jamais besoin de toucher aux défauts du framework. Si tu veux changer une valeur, tu la remets dans ton `master.config.php`, et c'est la tienne qui gagne.

## Attacher des classes

Certaines parties du framework (formulaires, validation, middlewares...) ne veulent pas d'un nom de classe complet partout dans le code. Elles passent par un alias déclaré en config, qui est transformé en classe au moment où on s'en sert.

```php
'class' => [
    'fields'      => [
        'editorjs' => \App\Form\Fields\EditorJsField::class,
    ],
    'rules'       => [
        // alias de règle de validation => classe
    ],
    'middlewares' => [
        // alias utilisé dans #[Middleware('Alias', ...)] => classe
    ],
],
```

- `class.fields` : les types de champ utilisables dans un `FormBuilder` (`$this->add('slug', 'editorjs')`).
- `class.rules` : les règles de validation utilisables dans `$form->validate()`.
- `class.middlewares` : alias → classe, utilisé par le pipeline de middlewares et par l'attribut `#[Middleware('Alias', ...)]`.

Ça t'évite de retaper le namespace complet à chaque fois, et ça permet de changer l'implémentation derrière un alias sans toucher au code qui l'utilise. Les autres alias du même genre (`class.gates`, `class.parts`) sont expliqués dans leurs chapitres ([13 Auth](013-Auth.md), [10 View](010-View.md)).

## Récupération des informations et utilisation

En plus de `Config::get()` et `Config::set()`, il y a des raccourcis pour les valeurs qu'on va chercher souvent :

```php
Config::getDbConf('default');      // config de connexion mongo
Config::getModuleList();           // ['App', ...] la liste des modules actifs
Config::getDefaultNamespace();     // 'App'
Config::getDefaultAction();        // 'index'
Config::getDebug();                // niveau de debug
Config::getVersion();              // version de skankydev
```

Il y a aussi `Config::getCurrentNamespace()` et `Config::setCurrentNamespace()`. Elles retiennent le module de la route courante : c'est ce qui évite de redonner `'namespace' => 'Admin'` à chaque `$this->url(...)` quand tu restes dans le même module ([06 Le Routing](006-Le-Routing.md)). Sans module courant, c'est le module par défaut qui sert.

## Sous le capot

`Config::initConf()` fait la fusion avec `array_replace_recursive`, dans cet ordre : les défauts du framework, puis la config de chaque module (dans l'ordre de la liste `Module`, donc un module déclaré plus tard écrase un module déclaré avant), puis `master.config.php`.

Ton `master.config.php` n'a besoin de rien de spécial : il retourne simplement son tableau (`return [...]`, ou `$conf = [...]; return $conf;`, les deux marchent). Un module sans fichier `Config/config.php` est simplement ignoré.
