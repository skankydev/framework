# SkankyDev

**English** · [Français](README.fr.md)

> A small homemade PHP MVC framework, built to do CRUD on MongoDB without the headache.

![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)
![MongoDB](https://img.shields.io/badge/MongoDB-5.0%2B-brightgreen.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)
[![Packagist](https://img.shields.io/packagist/v/skankydev/framework.svg)](https://packagist.org/packages/skankydev/framework)
![Last commit](https://img.shields.io/github/last-commit/skankydev/framework.svg)
[![Tests](https://github.com/skankydev/framework/actions/workflows/tests.yml/badge.svg)](https://github.com/skankydev/framework/actions/workflows/tests.yml)
[![Why PHP](https://img.shields.io/badge/Why_PHP-in_2026-7A86E8?style=flat&labelColor=18181b)](https://whyphp.dev)


I built it to understand how frameworks work, and I keep evolving it project after project. It's simple, it has its conventions, and it lets me focus on the business logic with peace of mind.

## Getting started

The easiest way is the [starter](https://github.com/skankydev/starter), a ready-to-use project:

```bash
composer create-project skankydev/starter my-project
```

## The docs

They live in the [`docs/en/`](docs/en/000-Table-of-Contents.md) folder, versioned with the code (and in French in [`docs/fr/`](docs/fr/000-table_des_matiere.md)). Start with:

- [01 Philosophy](docs/en/001-Philosophy.md): where it comes from;
- [02 Installation](docs/en/002-Installation.md): install the starter and get a CRUD running;
- [03 Introduction](docs/en/003-Introduction.md): the journey of a request and the folder structure.

## Requirements

- PHP 8.4 or later, with the `mongodb` and `intl` extensions;
- MongoDB.

## Tests

```bash
composer install
composer test
```

The integration tests need a running MongoDB ([20 Tests](docs/en/020-Tests.md)).

## License

MIT, see [LICENSE.txt](LICENSE.txt).
