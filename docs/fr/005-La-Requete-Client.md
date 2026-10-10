# 05 La Requête Client

Tout ce qui rentre dans l'appli (GET, POST, JSON, cookies, fichiers, headers...) passe par une seule classe : `Request`. Je l'ai fait comme ça pour pas courir après `$_GET`, `$_POST` et `$_SERVER` dans tous les coins.

Bonne nouvelle, t'as quasi jamais besoin d'aller la chercher : tu la demandes en paramètre de ton action et elle arrive toute seule.

```php
public function show(Request $request, Post $post) { ... }
```

(Si vraiment t'en as besoin ailleurs, c'est un singleton : `Request::getInstance()`.)

## Les infos de base

Qui appelle, d'où, avec quoi :

```php
use SkankyDev\Http\Request;
$request = Request::getInstance();
$request->method();      // 'GET', 'POST', ...
$request->uri();         // '/module/show/abc' (sans la query string)
$request->fullUri();     // pareil mais avec le ?truc=machin
$request->scheme();       // 'http' ou 'https'
$request->host();        // 'skankyblog.local'
$request->ip();          // gère le X-Forwarded-For si t'es derrière un proxy
$request->userAgent();
```

Et trois petits tests qui évitent de farfouiller dans les headers à la main :

```php
$request->isAjax();      // header X-Requested-With
$request->isJson();      // Content-Type: application/json
$request->wantsJson();   // Accept: application/json
```

`wantsJson()` est le plus utile : c'est lui qui permet de répondre en JSON à un appel AJAX et en HTML à une navigation classique, avec la même action (voir [11 Les Réponses](011-Les-Reponses.md)).

## Post, Get, et les fichiers

Chacun a sa méthode, avec une valeur par défaut si tu veux :

```php
$request->query('page', 1);    // un paramètre GET
$request->post('email');       // un paramètre POST
$request->file('avatar');      // un fichier uploadé
$request->header('accept');    // un header, en minuscule
$request->cookie('session_id');
```

Sans clé, tu récupères le tableau complet : `$request->query()` te rend tout le `$_GET`.

Et si tu t'en fiches de savoir par où la valeur est arrivée, il y a `input($key, $default)` : il cherche dans le POST, puis le body JSON, puis le GET, dans cet ordre. Il y a même un raccourci magique, `$request->email` fait `input('email')` derrière.

## Les fichiers uploadés

`$request->file('avatar')` te rend un objet `UploadedFile`, ou `null` si le champ n'existe pas ou a été laissé vide. Pour un champ multiple (`name="docs[]"`), tu récupères une liste d'`UploadedFile`.

```php
$file = $request->file('img');
if ($file && $file->isValid()) {
    $persona->img = $file->store('img');   // StoredFile, rangé dans {upload.folder}/img/
}
```

Ce que l'objet sait te dire :

- `isValid()` / `errorMessage()` : l'envoi a marché ? Sinon, pourquoi (fichier trop gros pour le serveur, envoi partiel…). Les codes `UPLOAD_ERR_*` de PHP sont traduits dans la langue courante (domaine `skankydev`, voir [21](021-I18n.md)).
- `mimeType()` : le **vrai** type, lu dans le contenu du fichier (`finfo`), pas celui annoncé par le navigateur.
- `extension()` : l'extension déduite de ce vrai type (`jpg`, `png`, `pdf`…, `bin` si inconnu).
- `isImage()`, `dimensions()` : `[largeur, hauteur]` pour une image.
- `clientName()`, `clientExtension()`, `size()` : le nom d'origine et la taille, pour l'affichage.
- `store($sousDossier = '', $nom = null)` : déplace le fichier dans un sous-dossier du dossier d'upload (créé au besoin) sous un nom aléatoire (ou `$nom`) + l'extension sûre, et te rend un `StoredFile`. Le sous-dossier est relatif (`'img'`, `'media/2026'`) : un `..` est refusé, impossible de sortir du dossier d'upload.

Le dossier d'upload et son URL publique viennent de la config ([04](004-Config.md)), à surcharger dans `master.config.php` si besoin :

```php
'upload' => [
    'folder' => UPLOAD_FOLDER,   // public/upload par défaut
    'url'    => '/upload',       // ou 'https://cdn.mondomaine.com' le jour où tu passes sur un CDN
],
```

Pourquoi tant de méfiance ? Parce que tout ce qui vient du navigateur se falsifie. Si on gardait l'extension du nom envoyé, un `photo.php` atterrirait dans le dossier d'upload (servi par le serveur web) et deviendrait exécutable : n'importe qui pourrait lancer du PHP sur ton serveur. Ici, l'extension vient du contenu, donc un script déguisé en image finit en `.bin` ou en `.txt`, jamais en `.php`. Le SVG est volontairement absent de la liste : il peut contenir du JavaScript.

### StoredFile

`StoredFile` est un `EmbeddedDocument` ([09.1](009.1-Persistable.md)) : tu le ranges tel quel dans un document, et Mongo te le rend typé à la relecture.

```php
class Persona extends MasterDocument {
    public ?StoredFile $img = null;
}

// dans la vue
<img src="<?= e($persona->img?->url()) ?>" alt="">
```

Il ne stocke que le chemin relatif au dossier d'upload (`img/3f2a….png`), le nom d'origine, le type, le poids, et les dimensions pour une image. L'URL n'est **jamais stockée** : `url()` la calcule avec `upload.url` + le chemin (`url(true)` pour l'avoir en absolu, avec le domaine de la requête). Pareil pour `fullPath()` (le chemin sur le disque), avec `upload.folder`. Tu changes de domaine, tu déplaces les fichiers ou tu passes sur un CDN : tu changes la config, rien à migrer en base. Il y a aussi `isImage()`, `extension()` et `delete()` pour supprimer le fichier.

Pour valider un fichier (type, poids, image), il y a les règles `file`, `image`, `mimes:…` et `max_size:…` ([12.2](012.2-La-Validation.md)).

## La Session

Pour la session, j'ai fait une classe statique, `Session`, qui cause directement à `$_SESSION`. Les tableaux imbriqués, je les parcours avec des points : `user.id` plutôt que `$_SESSION['user']['id']`. Plus court, plus lisible.

```php
use SkankyDev\Utilities\Session;

Session::set('user.id', $id);
Session::get('user.id');
Session::delete('user.id');
Session::insert('log', 'nouvelle ligne');   // pousse dans un tableau, le crée si besoin
```

Le démarrage de la session, t'y touches pas : c'est un middleware qui s'en charge ([07 Les Middlewares](007-Les-Middlewares.md)). Il te reste juste à lire et écrire dedans.

## Les inputs et les flash

Un formulaire qui rate sa validation, c'est le cas classique : tu veux renvoyer l'utilisateur sur la page avec ce qu'il avait tapé et les erreurs. Tu rediriges avec `->withErrors(...)->withInput(...)` ([12 Les Forms](012-Les-Forms.md)) et ça met tout ça en session, pour une seule requête. Sur la page suivante, tu récupères avec :

```php
old('email');           // ce qu'il avait saisi, ou '' par défaut
error('email');         // l'erreur du champ, déjà dans un <span class="error">, ou '' si rien
```

Le flash, c'est exactement la même idée mais sans formulaire : un message qui vit une seule requête, genre « Connexion réussie ». Tu le poses, tu fais ta redirection, la page suivante l'affiche, et hop, il disparaît.

```php
// côté controller
return redirect(['name' => 'home'])->withFlash('success', 'Connexion réussie');

// côté vue
$msg = flash(); // ['type' => 'success', 'message' => '...'] ou null
```

Attention, une fois lu il est supprimé : affiche-le une seule fois par page.

## Sous le capot

Pour les curieux, ce que tu n'as pas besoin de savoir pour t'en servir mais qui explique pourquoi ça marche comme ça :

- **Les fichiers.** `$_FILES` a une structure ignoble dès qu'il y a un champ multiple (`name[]`) : PHP te donne un tableau de propriétés au lieu d'un tableau de fichiers. `Request` remet tout ça à l'endroit dans son constructeur et transforme chaque fichier en `UploadedFile`, d'où le `file('documents')` qui te rend direct une liste. Un champ laissé vide arrive quand même dans `$_FILES` (avec l'erreur `UPLOAD_ERR_NO_FILE`) : il est écarté, c'est pour ça que `file()` te rend `null`.
- **L'injection.** Si `Request` arrive toute seule dans tes actions, c'est le `MasterFactory` qui la résout (voir [08 Controller](008-Controller.md)). C'est un singleton, il prend le raccourci `getInstance()`.
- **Le flash.** Tout repose sur `Session::getAndClean($path)` : il lit une valeur et la supprime dans la foulée. C'est ça qui fait qu'un flash ne survit qu'une requête.
- **`Session::regenerate()`.** Elle change l'ID de session en gardant les données. À appeler à la connexion, pour pas garder un ID émis avant l'authentification (sinon, c'est la porte ouverte à la fixation de session).
