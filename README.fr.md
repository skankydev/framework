# SkankyDev

[English](README.md) · **Français**

Un petit framework PHP MVC maison, pensé pour faire du CRUD sur MongoDB sans se prendre la tête.

Je l'ai construit pour comprendre comment marchent les frameworks, et je le fais évoluer projet après projet. Il est simple, il a ses conventions, et il me laisse me concentrer sur la logique métier l'esprit tranquille.

## Pour démarrer

Le plus simple, c'est le [starter](https://github.com/skankydev/starter), un projet prêt à l'emploi :

```bash
composer create-project skankydev/starter mon-projet
```

## La doc

Elle est dans le dossier [`docs/fr/`](docs/fr/000-table_des_matiere.md), versionnée avec le code. Commence par :

- [01 Philosophie](docs/fr/001-Philosophie.md) : d'où ça vient ;
- [02 Installation](docs/fr/002-Installation.md) : installer le starter et avoir un CRUD qui tourne ;
- [03 Introduction](docs/fr/003-Introduction.md) : le voyage d'une requête et la structure des dossiers.

## Ce qu'il faut

- PHP 8.4 ou plus, avec les extensions `mongodb` et `intl` ;
- MongoDB.

## Les tests

```bash
composer install
composer test
```

Les tests d'intégration ont besoin d'un MongoDB qui tourne ([20 Les tests](docs/fr/020-Les-Tests.md)).

## Licence

MIT, voir [LICENSE.txt](LICENSE.txt).
