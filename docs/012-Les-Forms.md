# 12 Les Forms

## Utilisation du FormBuilder

Un formulaire, c'est une classe qui étend `FormBuilder` et qui implémente `build()`, là où tu déclares tes champs :

```php
class ArticleForm extends FormBuilder {
    public function build(): void {
        $this->add('title', 'text', ['label' => 'Titre', 'rules' => 'required|max_length:120']);
        $this->add('body', 'textarea', ['label' => 'Contenu', 'rules' => 'required']);
        $this->submit('Enregistrer');
    }
}
```

Tu l'instancies avec l'action du formulaire (un tableau de route, passé à l'`UrlBuilder` [06.1](006.1-UrlBuilder.md), ou directement une URL en string), et tu l'affiches :

```php
// controller
$form = new ArticleForm(['name' => 'article-store']);
return view('article.create', ['form' => $form]);
```

```php
// vue
<?= $form->render() ?>
```

`render()` sort le `<form>` complet : la balise ouvrante (avec le token CSRF ajouté tout seul pour une méthode POST, voir [12.3 CSRF](012.3-CSRF.md)), tous les champs, le bouton submit, la balise fermante. Tu n'as pas à appeler `build()` toi-même : il tourne tout seul au premier besoin (via `render()`, `validate()` ou `only()`).

Si tu veux un layout plus perso que l'enchaînement automatique des champs, tu as les morceaux séparés : `$form->open()`, `$form->renderField('title')` un par un, puis `$form->close()`.

## Pré-remplissage

Pour un formulaire d'édition, `setData()` prend un objet (un Document, par exemple, [09 Model](009-Model.md)) ou un tableau. Ses propriétés deviennent les valeurs par défaut des champs :

```php
$form = new ArticleForm(['name' => 'article-update', 'params' => [$article->_id]]);
$form->setData($article);
```

Après un échec de validation, c'est géré pour toi : une nouvelle instance du même form retrouve toute seule les valeurs saisies au tour d'avant et les erreurs de chaque champ ([05](005-La-Requete-Client.md) et [11](011-Les-Reponses.md) expliquent le mécanisme). Tu n'as rien de spécial à écrire dans `build()`.

## Et la validation avec le FormBuilder

```php
#[Middleware('PostOnly')]
public function store(Request $request) {
    $input = $request->input();
    $form  = new ArticleForm(['name' => 'article-store']);

    if (!$form->validate($input)) {
        return redirect(['name' => 'article-create'])
            ->withErrors($form->getErrors())
            ->withInput($input);
    }

    $article = new Article($form->only($input));
    ArticleCollection::_save($article);
    return redirect(['name' => 'article-show', 'params' => [$article->_id]])
        ->withFlash('success', 'Enregistré');
}
```

`validate($data)` récupère les règles déclarées sur chaque champ (le `'rules' => '...'` passé à `add()`) et les fait tourner avec le `Validator` ([12.2 La Validation](012.2-La-Validation.md)). En cas d'échec, les erreurs sont posées sur le form et sur chaque champ (donc visibles directement si tu réaffiches le même form), et `getErrors()` te les rend pour les flasher en session avant la redirection.

`only($data)` ne garde que les clés déclarées comme champs du form. C'est un garde-fou contre le *mass assignment* quand tu construis un Document directement depuis l'input brut (`new Article($form->only($input))`) : seuls les champs que *toi* tu as déclarés dans `build()` peuvent atterrir sur le Document, pas n'importe quoi d'autre envoyé dans le POST.

Voir aussi : [12.1 Les Fields](012.1-Les-Fields.md) · [12.2 La Validation](012.2-La-Validation.md) · [12.3 CSRF](012.3-CSRF.md)
