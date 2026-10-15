# 02 Installation

On installe, on lance, et on a un CRUD qui tourne avant la fin du café. Promis, je l'ai testé de bout en bout.

## Ce qu'il te faut

- **PHP 8.4 ou plus** ([php.net/downloads](https://www.php.net/downloads), ou [windows.php.net](https://windows.php.net/download/) sous Windows), avec deux extensions :
  - `mongodb`, en version 2.4.1 ou plus ([installation sur php.net](https://www.php.net/manual/fr/mongodb.installation.php)) ;
  - `intl`, pour les traductions ([21 I18n](021-I18n.md)). Elle est fournie avec PHP, il suffit souvent de l'activer dans le `php.ini`.
- **Composer** ([getcomposer.org/download](https://getcomposer.org/download/)).
- **Un MongoDB qui tourne** : le [Community Server](https://www.mongodb.com/try/download/community) en local suffit largement. [Compass](https://www.mongodb.com/products/tools/compass) est pratique pour voir ce qu'il y a dans ta base.
- Un serveur web : Apache avec `mod_rewrite`, ou le serveur intégré de PHP pour essayer.
- *Bonus* : **Node.js et npm** ([nodejs.org](https://nodejs.org/)). Le starter est livré avec le CSS et le JS déjà compilés, tu n'en as besoin que pour modifier `src_front/` et recompiler avec Vite.

### Les pièges classiques

- **Trois « MongoDB » différents.** Le serveur MongoDB (la base), l'extension PHP `mongodb` (le pilote, en C) et la bibliothèque `mongodb/mongodb` (en PHP). Il faut les trois, et Composer ne s'occupe que de la dernière.
- **L'extension sous Windows.** Elle se télécharge en DLL sur la page [releases du driver](https://github.com/mongodb/mongo-php-driver/releases). Prends celle qui correspond exactement à ton PHP : même version (8.4), même architecture (x64), et TS ou NTS selon ton build (`php -i` te le dit, ligne « Thread Safety »). Copie `php_mongodb.dll` dans le dossier `ext/` de PHP, puis ajoute `extension=mongodb` dans le `php.ini`.
- **Le bon `php.ini`.** `php --ini` te dit lequel est chargé. Attention, le PHP en ligne de commande et celui d'Apache n'utilisent pas forcément le même : si ça marche dans le terminal mais pas dans le navigateur, c'est souvent ça.
- **Sous Linux et Mac**, passe par [PIE](https://github.com/php/pie), qui remplace PECL : `pie install mongodb/mongodb-extension`. Le paquet de ta distribution marche aussi, s'il est assez récent.

### Vérifie que tout est prêt

Avant de créer le projet, chaque commande doit répondre :

```bash
php -v                 # 8.4 ou plus
php -m                 # doit lister mongodb et intl
php --ri mongodb       # version de l'extension, 2.4.1 ou plus
composer --version
```

Pour MongoDB, connecte-toi à `mongodb://localhost:27017` avec Compass, ou lance `mongosh` s'il est installé (il est fourni à part du serveur).

## Créer le projet

Le projet de départ s'appelle le **starter**. Tu le récupères avec Composer :

```bash
composer create-project skankydev/starter mon-projet
cd mon-projet
```

Et si tu as installé Node.js, pour pouvoir retoucher le front :

```bash
npm install
```

Le framework lui-même (`skankydev/framework`) arrive tout seul dans `vendor/`, comme n'importe quelle dépendance. Tu ne le vois pas dans ton projet, et c'est fait exprès : ton dossier ne contient que *ton* code. Pour le mettre à jour plus tard :

```bash
composer update skankydev/framework
```

## Régler le .env

`composer create-project` a copié `.env.dist` en `.env` pour toi. Ouvre-le et règle au moins ta base :

```ini
APP_DEBUG=1

DB_MONGO_HOST=localhost
DB_MONGO_PORT=27017
DB_MONGO_DATABASE=MonProjet
```

`APP_DEBUG=1` te donne les pages d'erreur détaillées, parfait en dev. En production, tu mets `0` ([14 La gestion des erreurs](014-Gestion-des-Erreurs.md)). Le `.env` ne se commite jamais : c'est là que vivent tes secrets.

## Lancer le site

Pour essayer, le serveur intégré de PHP suffit :

```bash
php -S localhost:8000 -t public
```

Ouvre `http://localhost:8000` : tu dois tomber sur la page d'accueil du starter. Si tu vois « Ça tourne. Bravo. », c'est gagné.

Avec Apache, pointe le document root sur le dossier `public/` (le `.htaccess` à la racine redirige vers `public/` si tu ne peux pas le faire). Le seul dossier exposé au monde, c'est `public/`.

Pendant que tu retouches le CSS ou le JS (Node.js requis), laisse tourner dans un autre terminal :

```bash
npm run dev
```

Il recompile à chaque modification de `src_front/`. Quand tu as fini, `npm run build` produit la version finale dans `public/dist/`.

## Un CRUD en 10 minutes

C'est là que `php craft` entre en jeu ([16 Craft et CLI](016-Craft-et-CLI.md)). Tu lui donnes le nom de ton document, il te pose quelques questions sur les champs, et il génère tout le reste :

```bash
php craft crud-maker Article
```

Il te demande, pour chaque champ : son nom, son type (tu tapes le numéro du menu : `0` pour `string`, `1` pour `int`, etc.), et s'il est requis. Pour terminer, tu laisses le nom du champ vide.

```
Nom du champ ? title
0 : string   1 : int   2 : float   3 : bool ...
Type du champ ?  0
Requis ? (y/n) y

Nom du champ ? body
Type du champ ?  0
Requis ? (y/n) n

Nom du champ ?            <- vide, on a fini
```

Et il écrit huit fichiers :

| Fichier | Rôle |
|---|---|
| `src/App/Model/Document/Article.php` | le Document : la valeur ([09 Model](009-Model.md)) |
| `src/App/Model/ArticleCollection.php` | la Collection : la parole à MongoDB |
| `src/App/Controller/ArticleController.php` | `index`, `create`, `store`, `show`, `edit`, `update`, `delete` |
| `src/App/Form/ArticleForm.php` | le formulaire, avec ses règles de validation ([12 Les Forms](012-Les-Forms.md)) |
| `src_front/view/article/{index,create,edit,show}.php` | les quatre vues |

Pas de route à écrire : le routage par convention s'occupe de tout ([06 Le Routing](006-Le-Routing.md)). Va sur `http://localhost:8000/article` et tu as :

- la liste paginée des articles ;
- le formulaire de création (`/article/create`), avec la protection CSRF déjà branchée ([12.3 CSRF](012.3-CSRF.md)) ;
- l'affichage, l'édition (avec le formulaire pré-rempli) et la suppression ;
- la validation : si tu laisses `title` vide, tu reviens sur le formulaire avec l'erreur et ce que tu avais tapé.

Tout ce code est à toi. Il est là pour t'éviter la page blanche, pas pour t'enfermer : change les vues, ajoute des champs, renomme ce que tu veux.

Si ta Collection déclare des index Mongo (`getIndexes()`), la commande `php craft db-sync` les crée.

## Et si ça ne marche pas

- **Page blanche ou erreur de connexion** : vérifie que MongoDB tourne, et les `DB_MONGO_*` du `.env`.
- **`composer create-project` refuse d'installer `mongodb/mongodb`** : l'extension `mongodb` est absente ou trop ancienne. Vérifie avec `php --ri mongodb`.
- **Le site s'affiche sans style** : `public/dist/` est vide ou absent. Relance `npm run build`.
- **404 partout sauf l'accueil** (sous Apache) : `mod_rewrite` n'est pas activé, ou `AllowOverride` est à `None`.
