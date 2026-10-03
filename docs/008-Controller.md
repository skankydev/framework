# 08 Controller

Un controller, c'est une classe qui étend `MasterController`, avec une méthode par action. Pour l'instant `MasterController` ne fait rien de spécial : c'est juste le socle commun, là pour le jour où j'aurai besoin d'y accrocher un truc partagé.

```php
namespace App\Controller;

use App\Model\Document\Post;
use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Request;

class PostController extends MasterController {
    public function show(Request $request, Post $post) {
        return view('post.show', ['post' => $post]);
    }
}
```

## Injection de dépendance

Dans une action, tu demandes juste ce dont tu as besoin en paramètre, et ça arrive tout seul. Pas besoin d'aller chercher `Request::getInstance()` ni de parser l'URL à la main :

```php
namespace App\Controller\Auth;

use App\Model\Document\User;
use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Request;

class PasswordController extends MasterController {
    public function reset(Request $request, User $user, string $token) {
        // $user    → retrouvé depuis l'ID dans l'URL (/password/reset/:user/:token)
        // $token   → le segment :token, tel quel
        // $request → la Request courante
    }
}
```

Ça marche aussi dans le constructeur du controller : demande une Collection en paramètre, elle arrive toute seule.

Si tu veux savoir comment il devine quoi mettre où, c'est dans « Sous le capot » juste en dessous.

## Attacher des middlewares

Avec l'attribut `#[Middleware(...)]`, sur la classe (toutes les actions) ou sur une méthode (cette action-là, en plus de ceux de la classe) :

```php
namespace App\Controller;

use SkankyDev\Controller\MasterController;
use SkankyDev\Http\Middleware\Attribute\Middleware;
use SkankyDev\Http\Request;

#[Middleware('Auth')]
class AdminController extends MasterController {

    #[Middleware('RateLimit', 'login', 5, TIME_MINUTE * 15)]
    public function login(Request $request) { ... }
}
```

Le détail (les middlewares livrés, comment en écrire un, l'alternative `setMiddlewares()` sur une route déclarée) est dans [07 Les Middlewares](007-Les-Middlewares.md).

## Sous le capot

Le controller est instancié, et son action appelée, par le `MasterFactory` : `Application::run()` fait `MasterFactory::_make($controller)` puis `MasterFactory::_call($controller, $action, $params)`. C'est lui qui devine, par reflection, quoi mettre dans chaque paramètre typé, dans cet ordre :

1. **une sous-classe de `MasterDocument`** (`Post $post`, `User $user`...) : il prend l'ID dans les params de la route (par nom si tu as nommé le segment `:post`, sinon par position) et fait `Post::find($id)`. Introuvable : `ModelNotFoundException` (404).
2. **une clé nommée existe dans les params** : utilisée telle quelle.
3. **un type natif, ou pas de type** (`string $token`, `int $page`...) : la valeur positionnelle des params de route, sinon la valeur par défaut du paramètre si tu en as mis une, sinon ça plante (`ClassNotFoundException`).
4. **toute autre classe** (`Request $request`, une Collection...) : résolue récursivement via `MasterFactory::make()`.

Les Collections et `Request` sont des singletons, donc `make()` prend le raccourci `getInstance()` plutôt que d'essayer de les instancier.
