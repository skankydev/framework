# 09 Model (Document + Collection + CRUD)

## Separating the collection from the value

In SkankyDev, a Model is always two classes working as a pair. I split them so the value stays a plain value, and everything that talks to the database is stored somewhere else:

- a **Document** (`src/App/Model/Document/User.php`): a class with typed public properties, it's just the value. It extends `MasterDocument`.
- a **Collection** (`src/App/Model/UserCollection.php`): it knows how to talk to MongoDB for that specific document (find, save, delete...). It extends `MasterCollection`.

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

The Collection is a singleton, so you call it statically with the `_` prefix (like the Router):

```php
UserCollection::_findById($id);
UserCollection::_save($user);
```

Or, even simpler, you ask for it as a parameter of a controller's constructor or action, and it shows up on its own ([08 Controller](008-Controller.md)).

## Simple and effective

The basic CRUD comes for free on any Collection:

```php
$collection->find(['status' => 'active']);           // array of Documents, already hydrated
$collection->findOne(['email' => $email]);
$collection->findById($id);
$collection->paginate($filter, Request::_paginateInfo());  // a Paginator ready for the view

Collection::_save($document);      // insert if no _id, update otherwise (only the modified fields)
Collection::_saveSneaky($document); // save without triggering the behaviors (see 09.2)
Collection::_deleteOne($document);
Collection::_deleteById($id);
Collection::_delete($filter);       // in bulk

$collection->count($filter);
$collection->aggregate($pipeline);
$collection->updateMany($filter, ['$set' => [...]]); // raw Mongo update, no behaviors or propagation
```

Two methods to override in your Collection when you need them:

- `getIndexes()` declares the Mongo indexes to create. They're synced by the `db-sync` command.
- `getDisplayField()` describes the columns of the generic table (`part.table`). By default: all of the document's public fields, sortable.

## Stuff with documents

`MasterDocument::fill($data)` fills the properties from an array. It's what the constructor (`new User($data)`) and the `FormBuilder` use. The conversion follows the property's **declared type**:

- `ObjectId $module_id` ← a string: it's cast to `ObjectId`. If the value is empty, the default is kept rather than making up a bogus foreign key.
- `SomeBackedEnum $status` ← a string: passed to `tryFrom()`. Invalid value: the default is kept.
- `DateTime $last_login` ← an ISO string (the one a browser input sends back): parsed.
- everything else: assigned as is, PHP does its usual typing.

The same mechanism runs the other way, when you write to Mongo or read back: `DateTime` ⇄ `UTCDateTime`, enum ⇄ `->value`. For JSON (an AJAX response, for example): `ObjectId` becomes a string, the enum its value, and `DateTime` ISO 8601, a stable format that JS digests as is.

Two little things to know:

- **FK convention**: a foreign key (`module_id`, `user_id`...) is always typed `ObjectId`, never string. Aggregation `$lookup`s are cleaner with ObjectIds on both sides.
- **Magic getters**: `$document->field` works even if the field doesn't exist but there's a `getField()`. Handy to expose a computed value as if it were a property.

The link between a Document and its Collection is made by naming convention (`App\Model\Document\User` → `App\Model\UserCollection`). That's what lets `User::find($id)` find the right Collection, and the controller resolve `User $user` from the route's ID.

## Dirty tracking

Every `MasterDocument` remembers the state it was in when it was read from (or saved to) the database. So you can ask it what changed:

```php
$user = UserCollection::_findById($id);
$user->isDirty();            // false, nothing touched yet
$user->name = 'New name';
$user->isDirty();             // true
$user->isDirty('email');      // false, only name changed
$user->getDirty();            // ['name']
$user->getOriginal('name');   // the old value, typed as it was read (DateTime, enum...)
```

The big win is the `save`: instead of sending the whole document back, only the modified fields go to Mongo. If two requests load the same `User` at the same time and each modifies a different field, they no longer overwrite each other. And if nothing changed, `update()` doesn't even make a query (it returns `false`), and the behaviors' `afterUpdate` hooks don't fire.

A brand-new document (`new User(...)`, never read or saved) has no original state, so all its fields are considered modified. Makes sense: the `insert` has to write everything the first time.

## Under the hood

### Why `MongoDB\BSON\Persistable`

`MasterDocument` implements `Persistable` (on top of `JsonSerializable`). It's the interface that gives the Mongo driver the two methods `bsonSerialize()` and `bsonUnserialize()`. Without it, the driver does generic serialization: it works for writing, but when reading you get a `stdClass` and not a `User` instance, and you'd have to remap the fields by hand after every query.

With `Persistable`, you're the one who says how the object becomes BSON and how the BSON becomes your object again, and the driver calls it automatically. Result: `$collection->find($filter)` gives you typed `User`s directly, with the `DateTime`s and enums already converted back, zero mapping in your controllers.

### Rebuilding doesn't go through the constructor

A detail that matters: when reading, the driver creates the object "empty", without calling `__construct`, then fills the properties through `bsonUnserialize()`. That matters as soon as your constructor does anything other than plain initialization. `Token`, for example, generates a random value and a timestamp in its constructor:

```php
public function __construct(array $data = []) {
    $this->value = bin2hex(random_bytes(16));
    $this->time  = time();
    parent::__construct($data);
}
```

That code only runs when you do `new Token()` yourself (to generate a new reset token, for example). When `$user->reset_token` comes back from Mongo, the *stored* value and timestamp are assigned directly: the constructor isn't called again, so there's no new random value on every read. That's true for `MasterDocument` as well as for `EmbeddedDocument` ([09.1](009.1-Persistable.md)). If one day you put logic in a Document's constructor, remember that it will never fire again on reading.

### Dirty tracking

The original state is tucked away in an internal `WeakMap` (`$originals`). So it never shows up in `bsonSerialize()`, `jsonSerialize()` or `fill()`, and it disappears with the object, no memory leak to manage.

The comparison is done on the serialized BSON form, not with a naive `===` on the PHP properties, so it stays correct for a `DateTime`, an `ObjectId` or an embedded sub-document. `hasOriginal()` tells you whether the document has an original state (`false` for a document never read or saved).

See also: [09.1 The Persistable (EmbeddedDocument · EmbeddedSnapshot)](009.1-Persistable.md) · [09.2 Behaviors](009.2-Behaviors.md) · [09.3 Relations and propagation](009.3-Relations.md)
