# 02 Installation

On installe, on lance, et on a un CRUD qui tourne avant la fin du café. Promis, je l'ai testé de bout en bout.

## Ce qu'il te faut

- **PHP 8.4 ou plus**, avec les extensions `mongodb` et `intl` (pour les traductions, voir [21](021-I18n.md)). Prends une version récente de l'extension (2.4.1 ou plus) : les plus anciennes empêchent Composer d'installer la version corrigée de la bibliothèque `mongodb/mongodb` (une faille de sécurité a été corrigée dans la 2.4.1). Pour vérifier ta version : `php --ri mongodb`.
- **Composer**.
- **Un MongoDB qui tourne** (en local, ça suffit largement).
- **Node.js et npm**, pour compiler le CSS et le JS avec Vite.
- Un serveur web : Apache avec `mod_rewrite`, ou le serveur intégré de PHP pour essayer.

## Créer le projet

Le projet de départ s'appelle le **starter**. Tu le récupères avec Composer :

```bash
composer create-project skankydev/starter mon-projet
cd mon-projet
npm install
npm run build
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

Pendant que tu retouches le CSS ou le JS, laisse tourner dans un autre terminal :

```bash
npm run dev
```

Il recompile à chaque modification de `src_front/`.

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
- **Le site s'affiche sans style** : tu as oublié `npm run build`.
- **404 partout sauf l'accueil** (sous Apache) : `mod_rewrite` n'est pas activé, ou `AllowOverride` est à `None`.
