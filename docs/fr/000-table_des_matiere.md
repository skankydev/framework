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
- [09 Les Réponses](009-Les-Reponses.md) : `view()`, `redirect()`, `response()` et la négociation JSON/HTML.

## Les données

- [10 Model](010-Model.md) : le Document, la Collection, le CRUD et le dirty tracking.
  - [10.1 Le Persistable](010.1-Persistable.md) : EmbeddedDocument et EmbeddedSnapshot.
  - [10.2 Behaviors](010.2-Behaviors.md) : des hooks qui s'activent avec un trait.
  - [10.3 Relations et propagation](010.3-Relations.md) : `belongsTo`, `hasMany`, et la synchronisation des snapshots.

## L'affichage

- [11 View](011-View.md) : les vues, les layouts, les blocks et les Parts.
- [12 L'internationalisation](012-I18n.md) : `__()`, les fichiers de langue, ICU, choisir la langue.

## Formulaires et sécurité

- [13 Les Forms](013-Les-Forms.md) : le FormBuilder.
  - [13.1 Les Fields](013.1-Les-Fields.md) : les champs existants et comment en ajouter.
  - [13.2 La Validation](013.2-La-Validation.md) : les règles existantes et comment en créer.
  - [13.3 CSRF](013.3-CSRF.md) : le token, les formulaires et l'AJAX.
- [14 Auth](014-Auth.md) : login, logout, keepers, gates et providers.

## Quand ça se passe mal

- [15 La gestion des erreurs](015-Gestion-des-Erreurs.md) : le handler global, le debug, les 404.
- [16 Les Logs](016-Les-Logs.md) : ce qui est écrit, où, et le nettoyage.

## Hors de la page web

- [17 Craft et CLI](017-Craft-et-CLI.md) : `php craft`, le CrudMaker, le Publishable, créer une commande.
- [18 La Queue et les Jobs](018-Queue-et-Jobs.md) : les jobs, le worker, Supervisor.
- [19 Les Mails](019-Les-Mails.md) : l'objet mail, le sender, l'envoi par la queue.
- [20 Le client HTTP sortant](020-Client-HTTP-Sortant.md) : appeler une API externe.

## Le dessous du capot

- [21 Les tests](021-Les-Tests.md) : pourquoi seul le framework est couvert, et comment les lancer.
