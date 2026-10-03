# 06 Le Routing

C'est l'interprétation de l'URI pour que le dispatcher sache ce qu'il a à faire. Le framework propose deux façons de faire : par convention, ou par définition.

## La convention

Le schéma est simple : `http://exemple.com/{:module}/{:controller}/{:action}/{:param1}/.../{:paramN}`

Le module peut être absent si tu cherches à atterrir dans le module par défaut (App). Pareil pour l'action, elle vaut `index` par défaut.

Par exemple, l'URL

`http://mondomaine.com/persona/show/6a256b9f4f04f94e41005473`

ira chercher `PersonaController::show` en lui passant `6a256b9f4f04f94e41005473` en paramètre.

Si aucune route prédéfinie ne matche l'URI, on retombe sur la convention ci-dessus — c'est comme ça que le controller/action sont trouvés quand t'as rien déclaré explicitement.

Le framework retient tout seul le module de la route courante (`Config::getCurrentNamespace()`), donc t'as pas besoin de remettre `'namespace' => 'Admin'` dans le tableau link à chaque `$this->url(...)` tant que tu restes dans le même module. C'est de la tambouille interne, si tu veux le détail c'est dans [04 Config](004-Config.md).


## Les routes prédéfinies

Elles sont définies dans le fichier `routes/routes.php`.

On appelle le routeur pour les créer facilement, il utilise le trait singleton, donc tu peux préfixer les méthodes avec `_` pour les appeler plus facilement.

```php
use SkankyDev\Http\Routing\Router;
use App\Controller\HomeController;

Router::_add('/', [
	'controller' => HomeController::class,
	'action'     => 'index',
])->setName('home');
```

Et ainsi, `http://mondomaine.com/` dirigera l'application vers `HomeController::index`.

La méthode `Router::add` accepte trois paramètres : l'URI voulue, le tableau link, et les règles de validation regex.

Exemple avec une URL de récupération de mot de passe :

```php
Router::_add('/password/reset/:user/:token', [
	'controller' => PasswordController::class,
	'action'     => 'reset',
], [
	'user'  => '[a-f0-9]{24}',
	'token' => '[a-f0-9]{32}',
])->setName('password-reset');
```

Tu peux aussi ajouter des middlewares directement sur la route avec la méthode `setMiddlewares`. C'est une liste, tu peux donner soit un alias déclaré dans `class.middlewares`, soit directement le nom de classe complet :

```php
Router::_add('/admin', [...])->setMiddlewares(['Auth', 'Session']);
```

Et si le middleware a besoin d'arguments (comme `RateLimit` qui prend une clé, un nombre d'essais et une durée), même syntaxe qu'avec l'attribut `#[Middleware(...)]` sur un controller, mais en clé => tableau :

```php
Router::_add('/login', [...])->setMiddlewares([
	'RateLimit' => ['login', 5, TIME_MINUTE * 15],
]);
```

Voir [07 Les Middlewares](007-Les-Middlewares.md) pour le détail (attributs sur controller/action, alias, etc).
