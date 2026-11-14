# 13 Forms

## Using the FormBuilder

A form is a class that extends `FormBuilder` and implements `build()`, where you declare your fields:

```php
class ArticleForm extends FormBuilder {
    public function build(): void {
        $this->add('title', 'text', ['label' => 'Title', 'rules' => 'required|max_length:120']);
        $this->add('body', 'textarea', ['label' => 'Content', 'rules' => 'required']);
        $this->submit('Save');
    }
}
```

You instantiate it with the form's action (a route array, passed to the `UrlBuilder` [06.1](006.1-UrlBuilder.md), or a string URL directly), and you display it:

```php
// controller
$form = new ArticleForm(['name' => 'article-store']);
return view('article.create', ['form' => $form]);
```

```php
// view
<?= $form->render() ?>
```

`render()` outputs the complete `<form>`: the opening tag (with the CSRF token added on its own for a POST method, see [13.3 CSRF](013.3-CSRF.md)), all the fields, the submit button, the closing tag. You don't have to call `build()` yourself: it runs on its own the first time it's needed (through `render()`, `validate()` or `only()`).

If you want a more custom layout than the automatic sequence of fields, you have the separate pieces: `$form->open()`, `$form->renderField('title')` one by one, then `$form->close()`.

The button is a field like any other (`SubmitField`, type `submit`). `submit('Save')` is a shortcut for `add('submit', 'submit', ['label' => 'Save'])`: in a custom layout you place it with `$form->renderField('submit')`. You can also declare it yourself with `add()`, under any name. There is no default button: a form without `submit()` (or a `submit` field) has none. The `<button>` has no `name` attribute, so nothing is sent in the POST, and `only()` ignores it.

## Pre-filling

For an edit form, `setData()` takes an object (a Document, for example, [10 Model](010-Model.md)) or an array. Its properties become the fields' default values:

```php
$form = new ArticleForm(['name' => 'article-update', 'params' => [$article->_id]]);
$form->setData($article);
```

After a validation failure, it's handled for you: a new instance of the same form finds the values typed the previous time and each field's errors on its own ([05](005-The-Client-Request.md) and [09](009-Responses.md) explain the mechanism). You have nothing special to write in `build()`.

## And validation with the FormBuilder

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
        ->withFlash('success', 'Saved');
}
```

`validate($data)` gathers the rules declared on each field (the `'rules' => '...'` passed to `add()`) and runs them with the `Validator` ([13.2 Validation](013.2-Validation.md)). On failure, the errors are set on the form and on each field (so they're visible right away if you display the same form again), and `getErrors()` gives them to you so you can flash them into the session before the redirect.

`only($data)` only keeps the keys declared as fields of the form. It's a safeguard against *mass assignment* when you build a Document straight from the raw input (`new Article($form->only($input))`): only the fields *you* declared in `build()` can land on the Document, not anything else sent in the POST.

See also: [13.1 Fields](013.1-Fields.md) · [13.2 Validation](013.2-Validation.md) · [13.3 CSRF](013.3-CSRF.md)
