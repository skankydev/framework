# SkankyDev, la doc

[English](../en/000-Table-of-Contents.md) · **Français**

Un petit framework PHP MVC maison, pensé pour faire du CRUD sur MongoDB sans se prendre la tête. Voilà le plan, dans l'ordre où on découvre les choses.

## Pour commencer

- [01 Philosophie](001-Philosophie.md) : d'où ça vient, où ça va, et pourquoi les bisous mouillés sont les meilleurs.
- [02 Installation](002-Installation.md) : installer le starter et avoir un CRUD qui tourne.
- [03 Introduction](003-Introduction.md) : le voyage d'une requête, la structure des dossiers, les modules.

## La requête, de l'entrée à la sortie

- [04 Config](004-Config.md) : la fusion des fichiers, les alias de classes, comment récupérer une valeur.
- [05 La Requête Client](005-La-Requete-Client.md) : les infos de la requête, la Session, les inputs et les flash.
- [06 Le Routing](006-Le-Routing.md) : la convention et les routes déclarées.
  - [06.1 L'UrlBuilder](006.1-UrlBuilder.md) : construire une URL sans l'écrire en dur.
- [07 Les Middlewares](007-Les-Middlewares.md) : comment ça marche, ceux qui existent, en ajouter un.
- [08 Controller](008-Controller.md) : l'injection de dépendance et les middlewares.
- [11 Les Réponses](011-Les-Reponses.md) : `view()`, `redirect()`, `response()` et la négociation JSON/HTML.

## Les données

- [09 Model](009-Model.md) : le Document, la Collection, le CRUD et le dirty tracking.
  - [09.1 Le Persistable](009.1-Persistable.md) : EmbeddedDocument et EmbeddedSnapshot.
  - [09.2 Behaviors](009.2-Behaviors.md) : des hooks qui s'activent avec un trait.
  - [09.3 Relations et propagation](009.3-Relations.md) : `belongsTo`, `hasMany`, et la synchronisation des snapshots.

## L'affichage

- [10 View](010-View.md) : les vues, les layouts, les blocks et les Parts.
- [21 L'internationalisation](021-I18n.md) : `__()`, les fichiers de langue, ICU, choisir la langue.

## Formulaires et sécurité

- [12 Les Forms](012-Les-Forms.md) : le FormBuilder.
  - [12.1 Les Fields](012.1-Les-Fields.md) : les champs existants et comment en ajouter.
  - [12.2 La Validation](012.2-La-Validation.md) : les règles existantes et comment en créer.
  - [12.3 CSRF](012.3-CSRF.md) : le token, les formulaires et l'AJAX.
- [13 Auth](013-Auth.md) : login, logout, keepers, gates et providers.

## Quand ça se passe mal

- [14 La gestion des erreurs](014-Gestion-des-Erreurs.md) : le handler global, le debug, les 404.
- [15 Les Logs](015-Les-Logs.md) : ce qui est écrit, où, et le nettoyage.

## Hors de la page web

- [16 Craft et CLI](016-Craft-et-CLI.md) : `php craft`, le CrudMaker, le Publishable, créer une commande.
- [17 La Queue et les Jobs](017-Queue-et-Jobs.md) : les jobs, le worker, Supervisor.
- [18 Les Mails](018-Les-Mails.md) : l'objet mail, le sender, l'envoi par la queue.
- [19 Le client HTTP sortant](019-Client-HTTP-Sortant.md) : appeler une API externe.

## Le dessous du capot

- [20 Les tests](020-Les-Tests.md) : pourquoi seul le framework est couvert, et comment les lancer.
