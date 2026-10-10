# 14 La gestion des erreurs

## Le handler global

Toute exception non attrapée finit au même endroit : `ExceptionHandler::handle()`. `Application` l'installe dès son constructeur (`set_exception_handler`), et ajoute une fonction de shutdown qui rattrape même les erreurs fatales (`E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`) pour les lui repasser sous forme d'`ErrorException`. Résultat : pas de page blanche silencieuse.

```php
public function handle(Throwable $exception): void {
    $this->log($exception);
    $this->debug ? $this->renderDebug($exception) : $this->renderProduction($exception);
}
```

Le mode debug vient de la config `debug` (`.env` → `APP_DEBUG`), vrai ou faux, lu une fois au démarrage de `Application`.

## Le debug

Deux templates, choisis selon le mode :

- **`debug.php`** : la classe de l'exception, le message, fichier:ligne, la trace complète. Utile en dev, jamais en prod (ça fuite des infos internes).
- **`production.php`** : une page générique, rien d'exposé.

Les chemins sont configurables (`view.error` et `view.error_layout`). Par défaut, ce sont ceux livrés par le framework (le Publishable, voir [16 Craft et CLI](016-Craft-et-CLI.md)). Un projet peut publier les siens et pointer la config dessus, sans toucher au code.

## Le code de l'exception = le statut HTTP

C'est une convention dans tout le framework : le code que tu passes à l'exception sert de statut HTTP.

```php
throw new ModelNotFoundException("Document introuvable", 404);
```

## Le cas particulier des `NotFoundException`

`Application::run()` traite à part les exceptions de la famille `NotFoundException` (méthode, controller ou donnée introuvable : voir [09 Model](009-Model.md) et [06 Le Routing](006-Le-Routing.md)). Ce n'est pas un plantage, c'est un résultat attendu.

- **Code 404 et hors debug** : pas la page d'erreur générique, mais la vraie page 404 (`notFound()`, une vue normale avec layout, CSS et JS, voir [11 Les Réponses](011-Les-Reponses.md)). L'exception est quand même loggée. Cette page est la vue `error.404`, donc le fichier `src_front/view/error/404.php` **de ton projet** : le framework ne la fournit pas (le starter en contient une), et sans elle tu auras une erreur au lieu de ta 404.
- **Sinon** (mode debug, ou une `NotFoundException` réutilisée avec un autre code que 404) : `handle()` classique, comme n'importe quelle exception.

Cette dernière distinction compte : une `NotFoundException` avec un code différent de 404 reste une vraie erreur en prod, elle n'est pas maquillée en 404 silencieuse.

## Sous le capot

Les deux pages d'erreur (`debug.php` et `production.php`) sont rendues sans passer par `HtmlView` ni `Response` : pas de `Request`, pas de routing, pas de session. C'est volontaire : ce handler doit encore fonctionner quand le reste de l'appli est cassé, puisque c'est lui qui rattrape les cas où tout part en vrille.

```php
protected function renderErrorPage(string $template, Throwable $exception): void {
    http_response_code(500);
    $contentPath = Config::get('view.error') . DS . $template;
    $layoutPath  = Config::get('view.error_layout');
    // rendu par simple include + ob_start, pas par HtmlView
}
```

Voir aussi : [15 Les Logs](015-Les-Logs.md)
