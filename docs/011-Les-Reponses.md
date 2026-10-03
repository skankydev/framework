# 11 Les Réponses

## C'est la réponse

Une action de controller retourne toujours une `Response`, jamais d'`echo` direct. Pour en fabriquer une, il y a trois fonctions globales (dans `function.php`) :

```php
view('article.show', ['article' => $article]);   // une vue HTML (ou du JSON si le client le demande)
redirect(['name' => 'article-show', 'params' => [$id]]); // 302 + Location
response(['ok' => true]);                          // toujours du JSON, pour l'AJAX
```

Chacune te rend une instance de `Response`, chaînable (`->status()`, `->header()`, `->withFlash()`...), et c'est cette instance que ton action retourne. Rien ne part vers le navigateur tant que le framework n'a pas appelé `->send()` dessus, en fin de pipeline.

## `view()` / `redirect()` / `response()`

- **`view($name, $data)`** : rend un template ([10 View](010-View.md)). C'est la seule des trois qui a une vue associée.
- **`redirect($link)`** : construit l'URL avec l'`UrlBuilder` ([06.1 L'UrlBuilder](006.1-UrlBuilder.md)) et répond 302 avec le header `Location`. Tu peux le chaîner avec `withFlash()`, `withErrors()` ou `withInput()` pour préparer la page suivante.
- **`response($data)`** : une réponse de données, toujours en JSON, jamais de vue. C'est pour un endpoint AJAX.

## Négociation JSON/HTML

Tu n'as pas à écrire deux actions, une pour le HTML et une pour l'AJAX : `view()` choisit tout seul selon qui appelle.

- **Pas de vue associée** (`response()`) : toujours du JSON, quoi qu'il arrive.
- **Une vue existe** (`view()`) mais le client envoie `Accept: application/json` (typiquement une requête AJAX, voir `wantsJson()` dans [05 La Requête Client](005-La-Requete-Client.md)) : le JSON gagne, la vue n'est pas rendue.
- **Sinon** : la vue HTML normale.

Une seule action peut donc servir indifféremment une page classique et un appel AJAX :

```php
public function show(Article $article) {
    return view('article.show', ['article' => $article]);
    // navigation normale → la vue article.show
    // requête AJAX (Accept: application/json) → {"article": {...}} tel quel
}
```

## Chaînage utilitaire

Trois méthodes pensées pour préparer la page suivante après un `redirect()`. Elles passent toutes par la Session ([05](005-La-Requete-Client.md)) :

```php
return redirect(['name' => 'form-page'])
    ->withErrors($form->getErrors())   // les erreurs de validation, lues par error()
    ->withInput($input)                // les valeurs saisies, lues par old()
    ->withFlash('error', 'Corrige les champs en rouge');
```

`withErrors()` et `withInput()` vont ensemble : on les utilise après un échec de validation de formulaire ([12 Les Forms](012-Les-Forms.md)). `withFlash()` sert pour n'importe quel message ponctuel (succès, erreur, info), avec ou sans formulaire.

## Sous le capot

### `build()` vs `send()`

`Response::build()` construit le corps de la réponse :

```php
public function build(): self {
    if(Request::_wantsJson() || $this->viewName === ''){
        // JSON : $this->data encodé tel quel
    }else{
        // HTML : la vue est rendue (HtmlView)
    }
}
```

`build()` prépare le corps et les headers de contenu (`Content-Type`) sans rien envoyer. `send()`, appelé automatiquement en bout de pipeline (`Application::run()`), déclenche `build()` si besoin, puis écrit vraiment la réponse : code HTTP, headers, corps.

Un détail : `send()` ne construit **pas** de corps pour une redirection (3xx). Il n'y a rien à afficher, juste le header `Location` et un `exit` une fois les headers envoyés.

```php
if (!$this->built && ($this->statusCode < 300 || $this->statusCode >= 400)) {
    $this->build();
}
```

Donc `build()` tourne pour les 2xx (contenu normal) et pour les 4xx/5xx (une page d'erreur a un corps), mais pas entre les deux.

**Filet de sécurité** : si des headers sont déjà partis (un `echo` égaré avant, un warning PHP...) alors que la réponse est une redirection, `send()` retombe sur un `<script>window.location.href = '...'</script>` plutôt que de planter avec un header déjà expédié.
