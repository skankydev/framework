# 03 Introduction

SkankyDev est un petit framework PHP basé sur le modèle MVC qui va bien.
Il est utilisé pour faire des applications CRUD rapidement et pouvoir se concentrer sur la logique métier, l'esprit tranquille.

Avant de plonger dans les chapitres, un tour d'horizon : ce qui se passe quand une requête arrive, où sont rangées les choses, et comment on découpe un projet en modules.

## Le voyage d'une requête

Quand quelqu'un ouvre une page de ton site, voilà ce qui se passe, dans l'ordre :

1. `public/index.php` démarre l'autoload et `config/bootstrap.php` (qui charge le `.env`), puis lance `Application::run()`.
2. La config est assemblée ([04 Config](004-Config.md)) et le gestionnaire d'erreurs est mis en place ([14](014-Gestion-des-Erreurs.md)).
3. La requête est lue dans un objet `Request` ([05](005-La-Requete-Client.md)).
4. Le routeur cherche la route : d'abord celles que tu as déclarées dans `routes/routes.php`, sinon il retombe sur la convention `/module/controller/action/params` ([06 Le Routing](006-Le-Routing.md)).
5. Les middlewares s'exécutent, un par un ([07](007-Les-Middlewares.md)) : la session, le CSRF, et ceux que tu as posés.
6. Le controller est construit et son action appelée ([08](008-Controller.md)). Les paramètres de l'action sont devinés tout seuls : la `Request`, une Collection, ou un Document retrouvé depuis l'ID de l'URL.
7. L'action retourne une `Response` ([11](011-Les-Reponses.md)) : une vue, une redirection, ou du JSON. Le framework l'envoie.

Pour la ligne de commande (`php craft`), c'est le même principe : `craft` démarre `CliApplication`, qui assemble la même config puis exécute la commande demandée ([16](016-Craft-et-CLI.md)).

## La structure des dossiers

Voilà ce que tu as dans un projet créé avec le starter ([02 Installation](002-Installation.md)) :

```
config/            la config de ton projet
  bootstrap.php      charge le .env et règle la timezone
  master.config.php  ta config, elle a toujours le dernier mot
public/            le seul dossier visible depuis le web
  index.php          le point d'entrée
  dist/              le CSS et le JS compilés par Vite
routes/
  routes.php         tes routes déclarées à la main
src/
  App/               ton code (c'est le module « App », voir plus bas)
src_front/           tout ce qui touche à l'affichage
  js/                le JavaScript
  scss/              le style
  view/              les templates PHP (vues, layouts, parts)
logs/              les logs, créés au besoin
vendor/            les dépendances, dont le framework (skankydev/framework)
.env.dist          le modèle de ton .env
composer.json
craft              la ligne de commande : php craft
package.json       les dépendances front
vite.config.js     la compilation du front
LICENSE.txt
```

Une chose à remarquer : **le framework n'est pas dans ton projet**. Il vit dans `vendor/skankydev/framework/`, et tu n'y touches pas. Ce qui lui ressemble de plus près côté projet, c'est `config/master.config.php`, qui surcharge ses réglages par défaut.

Le framework a ses propres tests, mais ils vivent dans son dépôt, pas dans le tien ([20 Les tests](020-Les-Tests.md)).

## La construction par module

Un **module**, c'est un dossier de `src/` avec son propre namespace. Le starter n'en a qu'un, `App`, qui est le module par défaut. Il est déclaré dans la config :

```php
// config/master.config.php
'Module' => ['App'],
```

À l'intérieur, chaque module range ses affaires de la même façon (tout est facultatif, tu crées ce dont tu as besoin) :

```
src/App/
  Config/config.php   la config propre au module
  Controller/         les controllers
  Model/              les Collections, et Document/ pour les Documents
  Form/               les formulaires
  Mail/               les mails
  Middleware/         tes middlewares
  View/Part/          les classes des parts
  Command/            tes commandes craft
```

Le module intervient à trois endroits :

- **Dans l'URL.** Si le premier segment correspond à un module, c'est lui qui est utilisé : `/admin/user/index` appelle `Admin\Controller\UserController::index`. Sinon, c'est le module par défaut : `/user/index` appelle `App\Controller\UserController::index` ([06 Le Routing](006-Le-Routing.md)).
- **Dans la config.** Le fichier `Config/config.php` de chaque module est fusionné avec le reste ([04 Config](004-Config.md)).
- **Dans `php craft`.** Les commandes de chaque module sont découvertes toutes seules ([16](016-Craft-et-CLI.md)).

<!-- À VÉRIFIER (Simon) : « la construction par module n'est pas encore tranchée ». Ce chapitre décrit ce que fait le code aujourd'hui (CurrentRoute, Config::initConf, CliApplication). Limite connue : le CrudMaker génère toujours dans le module App. -->

## La suite

Le reste de la doc suit le même ordre que le voyage d'une requête : on commence par la config et la requête, on passe par le routing et les middlewares, puis le controller, le modèle et la vue. Viennent ensuite les formulaires, l'authentification, les erreurs, la ligne de commande, la queue, les mails, le client HTTP et enfin les tests. La [table des matières](000-table_des_matiere.md) te donne tout ça d'un coup d'œil.
