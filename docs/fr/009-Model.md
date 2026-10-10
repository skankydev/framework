# 09 Model (Document + Collection + CRUD)

## Séparer la collection de la valeur

Chez SkankyDev, un Model, c'est toujours deux classes qui bossent en duo. J'ai séparé les deux pour que la valeur reste une simple valeur, et que tout ce qui parle à la base soit rangé ailleurs :

- un **Document** (`src/App/Model/Document/User.php`) : une classe avec des propriétés publiques typées, c'est juste la valeur. Elle étend `MasterDocument`.
- une **Collection** (`src/App/Model/UserCollection.php`) : elle sait parler à MongoDB pour ce document précis (find, save, delete...). Elle étend `MasterCollection`.

```php
namespace App\Model\Document;

use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Model\Document\Traits\TimedTrait;
use DateTime;

class User extends MasterDocument {
    use TimedTrait;

    public string $name = '';
    public string $email = '';
    public DateTime|null $last_login = null;
}
```

```php
namespace App\Model;

use SkankyDev\Utilities\Traits\Singleton;
use SkankyDev\Model\MasterCollection;
use App\Model\Document\User;

class UserCollection extends MasterCollection {
    use Singleton;

    protected string $collectionName = 'users';
    protected string $documentClass  = User::class;
}
```

La Collection est un singleton, donc tu l'appelles en statique avec le préfixe `_` (comme le Router) :

```php
UserCollection::_findById($id);
UserCollection::_save($user);
```

Ou, plus simple encore, tu la demandes en paramètre du constructeur ou d'une action de controller, et elle arrive toute seule ([08 Controller](008-Controller.md)).

## Simple et efficace

Le CRUD de base, tu l'as gratuitement sur n'importe quelle Collection :

```php
$collection->find(['status' => 'active']);           // tableau de Document, déjà hydratés
$collection->findOne(['email' => $email]);
$collection->findById($id);
$collection->paginate($filter, Request::_paginateInfo());  // un Paginator prêt pour la vue

Collection::_save($document);      // insert si pas d'_id, update sinon (seulement les champs modifiés)
Collection::_saveSneaky($document); // save sans déclencher les behaviors (voir 09.2)
Collection::_deleteOne($document);
Collection::_deleteById($id);
Collection::_delete($filter);       // en masse

$collection->count($filter);
$collection->aggregate($pipeline);
$collection->updateMany($filter, ['$set' => [...]]); // update Mongo brut, sans behaviors ni propagation
```

Deux méthodes à surcharger dans ta Collection quand tu en as besoin :

- `getIndexes()` déclare les index Mongo à créer. Ils sont synchronisés par la commande `db-sync`.
- `getDisplayField()` décrit les colonnes du tableau générique (`part.table`). Par défaut : tous les champs publics du document, triables.

## Les trucs avec les documents

`MasterDocument::fill($data)` remplit les propriétés depuis un tableau. C'est ce qu'utilisent le constructeur (`new User($data)`) et le `FormBuilder`. La conversion suit le **type déclaré** de la propriété :

- `ObjectId $module_id` ← une string : elle est castée en `ObjectId`. Si la valeur est vide, on garde le défaut plutôt que de fabriquer une clé étrangère bidon.
- `SomeBackedEnum $status` ← une string : passée à `tryFrom()`. Valeur invalide : on garde le défaut.
- `DateTime $last_login` ← une string ISO (celle que renvoie un input du navigateur) : parsée.
- tout le reste : assigné tel quel, PHP fait son typage habituel.

La même mécanique tourne dans l'autre sens, quand tu écris dans Mongo ou que tu relis : `DateTime` ⇄ `UTCDateTime`, enum ⇄ `->value`. Pour le JSON (une réponse AJAX, par exemple) : `ObjectId` devient une string, l'enum sa valeur, et `DateTime` du ISO 8601, un format stable que le JS digère tel quel.

Deux petites choses à savoir :

- **Convention FK** : une clé étrangère (`module_id`, `user_id`...) est toujours typée `ObjectId`, jamais string. Les `$lookup` d'agrégation sont plus propres avec des ObjectId des deux côtés.
- **Les getters magiques** : `$document->champ` marche même si le champ n'existe pas mais qu'il y a un `getChamp()`. Pratique pour exposer une valeur calculée comme si c'était une propriété.

Le lien entre un Document et sa Collection se fait par convention de nom (`App\Model\Document\User` → `App\Model\UserCollection`). C'est ce qui permet à `User::find($id)` de trouver la bonne Collection, et au controller de résoudre `User $user` depuis l'ID de la route.

## Dirty tracking

Chaque `MasterDocument` se souvient de l'état dans lequel il a été lu (ou sauvé) en base. Tu peux donc lui demander ce qui a changé :

```php
$user = UserCollection::_findById($id);
$user->isDirty();            // false, rien touché encore
$user->name = 'Nouveau nom';
$user->isDirty();             // true
$user->isDirty('email');      // false, seul name a changé
$user->getDirty();            // ['name']
$user->getOriginal('name');   // l'ancienne valeur, typée comme à la lecture (DateTime, enum...)
```

Le gros intérêt, c'est le `save` : au lieu de renvoyer tout le document, seuls les champs modifiés partent vers Mongo. Si deux requêtes chargent le même `User` en même temps et modifient chacune un champ différent, elles ne s'écrasent plus. Et si rien n'a changé, `update()` ne fait même pas de requête (il renvoie `false`), et les hooks `afterUpdate` des behaviors ne se déclenchent pas.

Un document tout neuf (`new User(...)`, jamais lu ni sauvé) n'a pas d'état d'origine, donc tous ses champs sont considérés comme modifiés. Logique : l'`insert` doit tout écrire la première fois.

## Sous le capot

### Pourquoi `MongoDB\BSON\Persistable`

`MasterDocument` implémente `Persistable` (en plus de `JsonSerializable`). C'est l'interface qui donne au driver Mongo les deux méthodes `bsonSerialize()` et `bsonUnserialize()`. Sans elle, le driver fait de la sérialisation générique : ça marche pour écrire, mais à la lecture tu récupères un `stdClass` et pas une instance de `User`, et il faudrait remapper les champs à la main après chaque requête.

Avec `Persistable`, c'est toi qui dis comment l'objet devient du BSON et comment le BSON redevient ton objet, et le driver l'appelle automatiquement. Résultat : `$collection->find($filter)` te renvoie directement des `User` typés, avec les `DateTime` et les enums déjà reconvertis, zéro mapping dans tes controllers.

### La reconstruction ne passe pas par le constructeur

Un détail qui compte : à la lecture, le driver crée l'objet « à vide », sans appeler `__construct`, puis remplit les propriétés via `bsonUnserialize()`. C'est important dès que ton constructeur fait autre chose que de la simple init. `Token`, par exemple, génère une valeur aléatoire et un timestamp dans son constructeur :

```php
public function __construct(array $data = []) {
    $this->value = bin2hex(random_bytes(16));
    $this->time  = time();
    parent::__construct($data);
}
```

Ce code ne tourne que quand tu fais `new Token()` toi-même (pour générer un nouveau token de reset, par exemple). Quand `$user->reset_token` revient de Mongo, la valeur et le timestamp *stockés* sont assignés directement : le constructeur n'est pas rappelé, donc pas de nouvelle valeur aléatoire à chaque lecture. C'est vrai pour `MasterDocument` comme pour `EmbeddedDocument` ([09.1](009.1-Persistable.md)). Si un jour tu mets de la logique dans un constructeur de Document, retiens qu'elle ne se redéclenchera jamais à la relecture.

### Le dirty tracking

L'état d'origine est planqué dans une `WeakMap` interne (`$originals`). Il n'apparaît donc jamais dans `bsonSerialize()`, `jsonSerialize()` ou `fill()`, et il disparaît avec l'objet, sans fuite mémoire à gérer.

La comparaison se fait sur la forme BSON sérialisée, pas avec un `===` naïf sur les propriétés PHP, donc ça reste correct pour un `DateTime`, un `ObjectId` ou un sous-document embarqué. `hasOriginal()` te dit si le document a un état d'origine (`false` pour un document jamais lu ni sauvé).

Voir aussi : [09.1 Le Persistable (EmbeddedDocument · EmbeddedSnapshot)](009.1-Persistable.md) · [09.2 Behaviors](009.2-Behaviors.md) · [09.3 Relations et propagation](009.3-Relations.md)
