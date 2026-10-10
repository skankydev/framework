<?php 
/**
 * Copyright (c) 2025 SCHENCK Simon
 * 
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright     Copyright (c) SCHENCK Simon
 *
 */

namespace SkankyDev\Model\Document;

use DateTime;
use stdClass;
use JsonSerializable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Persistable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\Document;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use SkankyDev\Utilities\Traits\StringFacility;



class MasterDocument implements JsonSerializable, Persistable {

	use StringFacility;

	public $_id;

	/**
	 * Dirty tracking: BSON fingerprint of each field as it is stored in the database.
	 * Kept in a WeakMap rather than a property so nothing leaks into get_object_vars()
	 * (hence neither into bsonSerialize/jsonSerialize nor into fill),
	 * and the entry goes away with the document.
	 * @var \WeakMap<MasterDocument, array<string, string>>|null
	 */
	private static ?\WeakMap $originals = null;

	/**
	 * Loaded relations (belongsTo / hasMany, see MasterCollection::relations()), by name.
	 * Same idea as $originals: kept outside the object, so never persisted nor dirty.
	 * @var \WeakMap<MasterDocument, array<string, mixed>>|null
	 */
	private static ?\WeakMap $relations = null;


	/**
	 * Derives the fully qualified Collection class name from the Document class name.
	 * e.g. `App\Model\Document\Module` → `App\Model\ModuleCollection`
	 */
	static public function collectionName(): string {
		$name = get_called_class();
		$name = str_replace('Document\\', '', $name);
		$name .= 'Collection';
		return $name;
	}

	/**
	 * Shortcut to find this document by ID via its Collection.
	 * Used by MasterFactory for automatic model binding in controllers.
	 * @throws \Exception if the corresponding Collection class does not exist
	 */
	public static function find(string $id): ?static {
		$collectionClass = static::collectionName();
		if (!class_exists($collectionClass)) {
			throw new \Exception("Collection {$collectionClass} not found for " . static::class,404);
		}
		return $collectionClass::_findById($id);
	}

	/**
	 * Magic Methods user for get mutable
	 * Lookup order: property → already loaded relation → `getXxx()` getter →
	 * relation declared by the Collection (loaded on demand, then cached).
	 * @param  string $name the name of the property
	 * @return mixed        the property
	 */
	public function __get($name){
		if(isset($this->$name)){
			return $this->$name;
		}
		$loaded = self::$relations[$this] ?? [];
		if (array_key_exists($name, $loaded)) {
			return $loaded[$name];
		}
		$methods = get_class_methods($this);
		$getter = 'get'.$this->toCap($name,'_');
		if(in_array($getter,$methods) !== false){
			return $this->$getter();
		}
		$collection = static::collectionName();
		if (class_exists($collection) && array_key_exists($name, $collection::getInstance()->relations())) {
			$collection::getInstance()->loadRelations([$this], [$name]);
			return self::$relations[$this][$name];
		}
		return null;
	}

	/**
	 * Stores a loaded relation (called by MasterCollection::loadRelations()).
	 */
	public function setRelation(string $name, mixed $value): void {
		self::$relations ??= new \WeakMap();
		$loaded = self::$relations[$this] ?? [];
		$loaded[$name] = $value;
		self::$relations[$this] = $loaded;
	}

	/**
	 * Optionally fills the document from an array on construction.
	 * Note: Persistable documents are reconstructed via bsonUnserialize(), bypassing this constructor.
	 */
	public function __construct(array $data = []) {
		if(!empty($data)){
			$this->fill($data);
		}
	}


	/**
	 * Returns the declared type name of a property (single named type), or null
	 * if the property is untyped, has a union/intersection type, or doesn't exist.
	 * Drives type-aware (de)serialization without relying on naming conventions.
	 */
	private function propertyType(string $key): ?string {
		if (!property_exists($this, $key)) {
			return null;
		}
		$type = (new \ReflectionProperty($this, $key))->getType();
		return $type instanceof \ReflectionNamedType ? $type->getName() : null;
	}

	/**
	 * Fills document properties from an array, only for declared class properties.
	 * Conversion is driven by the property's declared type (Reflection):
	 * `ObjectId` properties cast strings to ObjectId, `BackedEnum` properties cast
	 * strings via tryFrom(). Scalars rely on PHP's coercive typing. `_id` is never
	 * mass-assignable.
	 */
	public function fill(array $data): static {
		foreach ($data as $key => $value) {
			if ($key === '_id' || !property_exists($this, $key)) {
				continue;
			}
			$type = $this->propertyType($key);

			if ($type === ObjectId::class) {
				if ($value instanceof ObjectId) {
					$this->{$key} = $value;
				} elseif (!empty($value)) {
					$this->{$key} = new ObjectId($value);
				}
				// valeur vide → on laisse le défaut, pas de FK bidon générée
			} elseif ($type !== null && is_subclass_of($type, \BackedEnum::class)) {
				$enum = $value instanceof \BackedEnum ? $value : $type::tryFrom($value);
				if ($enum !== null) {
					$this->{$key} = $enum;
				}
			} elseif ($type === DateTime::class) {
				if ($value instanceof DateTime) {
					$this->{$key} = $value;
				} elseif (!empty($value)) {
					try {
						// new DateTime parse l'ISO du navigateur (date "2026-06-20"
						// comme datetime-local "2026-06-20T14:30") et la plupart des formats.
						$this->{$key} = new DateTime($value);
					} catch (\Exception $e) {
						// chaîne de date non parsable → on laisse le défaut
					}
				}
			} else {
				$this->{$key} = $value;
			}
		}
		return $this;
	}

	/**
	 * Serializes the document for MongoDB storage.
	 * Converts DateTime to UTCDateTime and BackedEnum to its scalar value.
	 * ObjectId-typed properties already hold an ObjectId and are stored as-is.
	 * An empty `_id` (new document) is dropped so MongoDB generates one natively;
	 * MasterCollection::insert() then back-fills it from getInsertedId().
	 * Called automatically by the MongoDB driver on insert/update.
	 */
	public function bsonSerialize(): stdClass|Document|array {
		$prop = get_object_vars($this);
		if (empty($prop['_id'])) {
			unset($prop['_id']);
		}
		foreach ($prop as $key=>$value) {
			if($value instanceof DateTime){
				$prop[$key] = new UTCDateTime($value);
			}else if($value instanceof \BackedEnum){
				$prop[$key] = $value->value;
			}
		}
		return $prop;
	}

	/**
	 * Reconstructs the document from MongoDB data without going through the constructor.
	 * Called automatically by the MongoDB driver when reading documents.
	 * Converts BSON types (UTCDateTime, BSONArray, BSONDocument) to native PHP types.
	 */
	public function bsonUnserialize(array $data): void {
		unset($data['__pclass']);
		foreach ($data as $key => $value) {
			$value = $this->unserializeValue($key, $value);
			if ($value === null && is_subclass_of($this->propertyType($key) ?? '', \BackedEnum::class)) {
				continue; // invalid stored enum → keep the document's default
			}
			$this->{$key} = $value;
		}
		$this->syncOriginal();
	}

	/**
	 * Converts a value read from Mongo to the property's PHP type:
	 * BSON → native (convertBsonValue), then scalar → BackedEnum when the property
	 * is an enum (null if the value is invalid).
	 */
	private function unserializeValue(string $key, mixed $value): mixed {
		$value = $this->convertBsonValue($value);
		$type = $this->propertyType($key);
		if ($type !== null && is_subclass_of($type, \BackedEnum::class) && !($value instanceof \BackedEnum)) {
			return $type::tryFrom($value);
		}
		return $value;
	}

	/**
	 * Recursively converts a BSON value to its native PHP equivalent.
	 * UTCDateTime → DateTime, BSONArray/BSONDocument → array.
	 * UTCDateTime::toDateTime() renvoie toujours un DateTime en UTC, quel que
	 * soit date_default_timezone_set() — Mongo stocke des instants, pas un fuseau.
	 * On le reconvertit donc explicitement vers le fuseau par défaut de l'app
	 * pour l'affichage (ex: Europe/Paris), le stockage restant en UTC.
	 */
	private function convertBsonValue(mixed $value): mixed {
		if ($value instanceof UTCDateTime) {
			return $value->toDateTime()->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		}

		if ($value instanceof BSONArray || $value instanceof BSONDocument) {
			$array = $value->getArrayCopy();
			return array_map([$this, 'convertBsonValue'], $array);
		}

		return $value;
	}

	/**
	 * Serializes the document for JSON output.
	 * ObjectId fields are converted to strings, BackedEnum to their scalar value,
	 * and DateTime to an ISO 8601 string (format standard, exploitable tel quel en JS/API).
	 */
	public function jsonSerialize(): mixed {
		$data = get_object_vars($this);

		foreach ($data as $key => $value) {
			if($value instanceof ObjectId){
				$data[$key] = (string) $value;
			}else if($value instanceof \BackedEnum){
				$data[$key] = $value->value;
			}else if($value instanceof DateTime){
				$data[$key] = $value->format(DATE_ATOM);
			}
		}

		return $data;
	}

	/**
	 * Records the current state as the persisted state: no field is dirty anymore.
	 * Called on read (bsonUnserialize) and by MasterCollection after each write.
	 */
	public function syncOriginal(): void {
		self::$originals ??= new \WeakMap();
		self::$originals[$this] = $this->fingerprints();
	}

	/**
	 * True if the document's persisted state is known (read from the database or already saved).
	 * A hand-built document (`new`) has none: all its fields are dirty.
	 */
	public function hasOriginal(): bool {
		return self::$originals !== null && isset(self::$originals[$this]);
	}

	/**
	 * Fields changed since the last persisted state (newly added fields included).
	 * Comparison is done on the BSON form, so DateTime, ObjectId and
	 * sub-documents compare the way Mongo stores them.
	 * @return string[]
	 */
	public function getDirty(): array {
		$current = $this->fingerprints();
		if (!$this->hasOriginal()) {
			return array_keys($current);
		}
		$original = self::$originals[$this];
		$dirty = [];
		foreach ($current as $key => $print) {
			if (($original[$key] ?? null) !== $print) {
				$dirty[] = $key;
			}
		}
		return $dirty;
	}

	/**
	 * Value of a field in its persisted state (last read / save),
	 * typed as on a Mongo read (DateTime, enum, sub-documents…).
	 * Without argument: all original values, keyed by field.
	 * Each call returns a fresh copy; modifying it does not alter the original state.
	 * Null if the field did not exist or the document has no known state.
	 */
	public function getOriginal(?string $field = null): mixed {
		if (!$this->hasOriginal()) {
			return $field === null ? [] : null;
		}
		$original = self::$originals[$this];
		if ($field !== null) {
			return isset($original[$field]) ? $this->decodeFingerprint($field, $original[$field]) : null;
		}
		$values = [];
		foreach ($original as $key => $print) {
			$values[$key] = $this->decodeFingerprint($key, $print);
		}
		return $values;
	}

	/**
	 * True if the field (or, without argument, at least one field) has changed.
	 */
	public function isDirty(?string $field = null): bool {
		$dirty = $this->getDirty();
		return $field === null ? !empty($dirty) : in_array($field, $dirty, true);
	}

	/**
	 * Serialized values (Mongo-ready) of the changed fields only:
	 * this is the `$set` of an update. `_id` is never included (immutable).
	 */
	public function dirtyData(): array {
		$data = array_intersect_key($this->bsonSerialize(), array_flip($this->getDirty()));
		unset($data['_id']);
		return $data;
	}

	/**
	 * BSON fingerprint of each serialized field (raw bytes, comparable with ===).
	 * @return array<string, string>
	 */
	private function fingerprints(): array {
		$prints = [];
		foreach ($this->bsonSerialize() as $key => $value) {
			$prints[$key] = (string) Document::fromPHP(['v' => $value]);
		}
		return $prints;
	}

	/**
	 * Decodes a fingerprint back into a PHP value, with the same typeMap as the
	 * Mongo library (Persistable sub-documents are rebuilt via __pclass).
	 */
	private function decodeFingerprint(string $key, string $print): mixed {
		$decoded = Document::fromBSON($print)->toPHP([
			'root'     => 'array',
			'document' => BSONDocument::class,
			'array'    => BSONArray::class,
		]);
		return $this->unserializeValue($key, $decoded['v']);
	}

}