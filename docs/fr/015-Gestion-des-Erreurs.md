# 15 La gestion des erreurs

## Le handler global

Toute exception non attrapée finit au même endroit : `ExceptionHandler::handle()`. `Application` l'installe dès son constructeur (`ExceptionHandler::register()` : `set_exception_handler`), et ajoute une fonction de shutdown qui rattrape même les erreurs fatales (`E_ERROR`, `E_CORE_ERROR`, `E_COMPILE_ERROR`) pour les lui repasser sous forme d'`ErrorException`. Résultat : pas de page blanche silencieuse.

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

Les chemins sont configurables (`view.error` et `view.error_layout`). Par défaut, ce sont ceux livrés par le framework (le Publishable, voir [17 Craft et CLI](017-Craft-et-CLI.md)). Un projet peut publier les siens et pointer la config dessus, sans toucher au code.

Ces pages sont en anglais, en dur : elles ne passent pas par les traductions, pour que la page d'erreur ne puisse pas planter à son tour à cause d'un fichier de langue cassé ([12 L'internationalisation](012-I18n.md)). Les messages d'exception aussi sont en anglais.

Les arguments de chaque appel de la trace sont affichés par `debug()`, avec des limites serrées (profondeur, nombre d'entrées, longueur des chaînes) : une trace qui transporte un gros graphe d'objets reste une page légère.

## Les warnings, notices et dépréciations

`ExceptionHandler::register()` installe aussi `handleError()` (`set_error_handler`) : une erreur PHP non fatale n'est jamais silencieuse.

- **Toujours loggée** dans le fichier d'erreurs, une seule fois par requête pour un même message/fichier/ligne (un warning dans une boucle ne noie pas le log).
- **En debug** : affichée sur place, dans un bloc `.debug-message.debug-error` (mêmes classes CSS que `debug()`), à la place de la sortie brute de PHP. Pas d'affichage pour une requête JSON, le bloc casserait la réponse.
- **Hors debug** : rien n'est affiché.

L'opérateur `@` et `error_reporting` restent respectés.

## `debug()` et le `Dumper`

`debug($data, $message = '', $limits = [])` affiche un dump coloré, et choisit son rendu tout seul : un bloc HTML (classes `debug-*`) sur une page, du texte en couleurs ANSI dans un terminal. Le rendu vient de `SkankyDev\Utilities\Dumper` (`html()` / `cli()`), borné par construction : ce qui dépasse est remplacé par un marqueur `…`.

La détection se fait sur la SAPI (`Dumper::isCli()`). Pour les cas où elle se trompe (un test, un script CLI qui fabrique du HTML), `Dumper::forceMode('html')` ou `'cli'` l'impose, `forceMode(null)` rend la main à la détection.

| Limite | Défaut | Rôle |
|---|---|---|
| `depth` | 6 | niveaux d'imbrication |
| `items` | 100 | entrées par tableau / objet |
| `string` | 2000 | caractères par chaîne |
| `nodes` | 5000 | valeurs au total |

```php
debug($paginator, 'scores', ['depth' => 3, 'items' => 10]);
```

## En CLI

`CliApplication` enregistre le même handler, en mode terminal (`new ExceptionHandler($debug, cli: true)`). Une commande `craft` qui plante n'affiche donc plus la sortie brute de PHP :

- **Exception non attrapée ou erreur fatale** : classe, message et fichier:ligne en couleur, plus la trace en debug. Loggée comme côté web, avec la ligne de commande à la place de l'URL.
- **Warning, notice, dépréciation** : une ligne colorée, affichée même hors debug (celui qui lance la commande a le droit de la voir), et loggée.

## Le code de l'exception = le statut HTTP

C'est une convention dans tout le framework : le code que tu passes à l'exception sert de statut HTTP.

```php
throw new ModelNotFoundException("Document introuvable", 404);
```

## Le cas particulier des `NotFoundException`

`Application::run()` traite à part les exceptions de la famille `NotFoundException` (méthode, controller ou donnée introuvable : voir [10 Model](010-Model.md) et [06 Le Routing](006-Le-Routing.md)). Ce n'est pas un plantage, c'est un résultat attendu.

- **Code 404 et hors debug** : pas la page d'erreur générique, mais la vraie page 404 (`notFound()`, une vue normale avec layout, CSS et JS, voir [09 Les Réponses](009-Les-Reponses.md)). L'exception est quand même loggée. Cette page est la vue `error.404`, donc le fichier `src_front/view/error/404.php` **de ton projet** : le framework ne la fournit pas (le starter en contient une), et sans elle tu auras une erreur au lieu de ta 404.
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

Voir aussi : [16 Les Logs](016-Les-Logs.md)
