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

Pour les fichiers, tu n'as rien de spécial à faire, `$request->file('documents')` te rend directement un tableau de fichiers, chacun avec ses clés `name`, `type`, `tmp_name`... (le détail de pourquoi, en bas dans « Sous le capot »).

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

- **Les fichiers.** `$_FILES` a une structure ignoble dès qu'il y a un champ multiple (`name[]`) : PHP te donne un tableau de propriétés au lieu d'un tableau de fichiers. `Request` remet tout ça à l'endroit dans son constructeur, d'où le `file('documents')` qui te rend direct une liste de fichiers.
- **L'injection.** Si `Request` arrive toute seule dans tes actions, c'est le `MasterFactory` qui la résout (voir [08 Controller](008-Controller.md)). C'est un singleton, il prend le raccourci `getInstance()`.
- **Le flash.** Tout repose sur `Session::getAndClean($path)` : il lit une valeur et la supprime dans la foulée. C'est ça qui fait qu'un flash ne survit qu'une requête.
- **`Session::regenerate()`.** Elle change l'ID de session en gardant les données. À appeler à la connexion, pour pas garder un ID émis avant l'authentification (sinon, c'est la porte ouverte à la fixation de session).
