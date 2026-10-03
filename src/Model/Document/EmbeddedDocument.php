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

/**
 * Document imbriqué (embedded) : un Persistable typé destiné à vivre *dans* un
 * autre document (array ou propriété), pas dans une collection à lui.
 *
 * Contrairement à MasterDocument, il n'a ni `_id` ni accès à une Collection :
 * c'est une simple valeur structurée (ex. un Message dans Conversation.messages).
 * MongoDB stocke `__pclass` et reconstruit le graphe d'objets automatiquement
 * (cf. MasterJob, qui en hérite).
 *
 * La (dé)sérialisation est pilotée par les types déclarés (Reflection) :
 * DateTime ⇄ UTCDateTime, BackedEnum ⇄ ->value, ObjectId stocké tel quel.
 */
abstract class EmbeddedDocument implements JsonSerializable, Persistable {

	/**
	 * Remplit l'objet depuis un tableau (uniquement les propriétés déclarées).
	 * Note : la relecture Mongo passe par bsonUnserialize, pas par ce constructeur.
	 */
	public function __construct(array $data = []) {
		if (!empty($data)) {
			$this->fill($data);
		}
	}

	/**
	 * Remplit les propriétés déclarées depuis un tableau, en convertissant
	 * selon le type déclaré (ObjectId ← string, BackedEnum ← tryFrom, DateTime ← ISO).
	 */
	public function fill(array $data): static {
		foreach ($data as $key => $value) {
			if (!property_exists($this, $key)) {
				continue;
			}
			$type = $this->propertyType($key);

			if ($type === ObjectId::class) {
				if ($value instanceof ObjectId) {
					$this->{$key} = $value;
				} elseif (!empty($value)) {
					$this->{$key} = new ObjectId($value);
				}
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
						$this->{$key} = new DateTime($value);
					} catch (\Exception $e) {
						// date non parsable → on garde le défaut
					}
				}
			} else {
				$this->{$key} = $value;
			}
		}
		return $this;
	}

	/**
	 * Type déclaré (nommé) d'une propriété, ou null si non typée / union / absente.
	 */
	private function propertyType(string $key): ?string {
		if (!property_exists($this, $key)) {
			return null;
		}
		$type = (new \ReflectionProperty($this, $key))->getType();
		return $type instanceof \ReflectionNamedType ? $type->getName() : null;
	}

	/**
	 * Sérialise pour le stockage Mongo : DateTime → UTCDateTime, BackedEnum → value.
	 * Appelé automatiquement par le driver.
	 */
	public function bsonSerialize(): stdClass|Document|array {
		$prop = get_object_vars($this);
		foreach ($prop as $key => $value) {
			if ($value instanceof DateTime) {
				$prop[$key] = new UTCDateTime($value);
			} elseif ($value instanceof \BackedEnum) {
				$prop[$key] = $value->value;
			}
		}
		return $prop;
	}

	/**
	 * Reconstruit l'objet depuis Mongo sans passer par le constructeur.
	 * Appelé automatiquement par le driver à la lecture.
	 */
	public function bsonUnserialize(array $data): void {
		unset($data['__pclass']);
		foreach ($data as $key => $value) {
			$value = $this->convertBsonValue($value);
			$type = $this->propertyType($key);
			if ($type !== null && is_subclass_of($type, \BackedEnum::class) && !($value instanceof \BackedEnum)) {
				$value = $type::tryFrom($value);
				if ($value === null) {
					continue; // valeur stockée invalide → on garde le défaut
				}
			}
			$this->{$key} = $value;
		}
	}

	/**
	 * Convertit récursivement une valeur BSON en équivalent PHP natif.
	 * UTCDateTime → DateTime, BSONArray/BSONDocument → array.
	 * UTCDateTime::toDateTime() renvoie toujours un DateTime en UTC, quel que
	 * soit date_default_timezone_set() — Mongo stocke des instants, pas un fuseau.
	 * On le reconvertit donc explicitement vers le fuseau par défaut de l'app.
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
	 * Sérialise pour la sortie JSON : ObjectId → string, BackedEnum → value.
	 */
	public function jsonSerialize(): mixed {
		$data = get_object_vars($this);
		foreach ($data as $key => $value) {
			if ($value instanceof ObjectId) {
				$data[$key] = (string) $value;
			} elseif ($value instanceof \BackedEnum) {
				$data[$key] = $value->value;
			}
		}
		return $data;
	}

}
