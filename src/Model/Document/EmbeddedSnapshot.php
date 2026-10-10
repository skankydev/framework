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

use MongoDB\BSON\ObjectId;

/**
 * Partial copy of a document, embedded in another one (Extended Reference Pattern).
 * e.g. a UserSnapshot (name, avatar) in Post.author: the author is displayed without a query.
 *
 * The properties declared by the child class define the copied fields:
 *
 *     class UserSnapshot extends EmbeddedSnapshot {
 *         public string $name = '';
 *         public string $avatar = '';
 *     }
 *     $post->author = new UserSnapshot($user);
 *
 * `_id` references the source document; the source Collection uses it to find
 * and resync the snapshots when the source changes.
 * `_deleted` (framework field) flags a snapshot whose source has been deleted.
 */
abstract class EmbeddedSnapshot extends EmbeddedDocument {

	/** Reference to the source document. */
	public ?ObjectId $_id = null;

	/** True if the source document has been deleted (onDelete `mark`). */
	public bool $_deleted = false;

	/** Framework fields, never copied from the source. */
	private const FRAMEWORK_FIELDS = ['_id', '_deleted'];

	/**
	 * Builds the snapshot from the source document, or from an array.
	 * Note: Mongo reads go through bsonUnserialize, not this constructor.
	 * @throws \LogicException if the source document has no _id (never saved)
	 */
	public function __construct(array|MasterDocument $source = []) {
		if ($source instanceof MasterDocument) {
			$this->copyFrom($source);
			return;
		}
		parent::__construct($source);
	}

	/**
	 * Copies from the source the fields declared by the snapshot, plus its `_id`.
	 * A field missing from the source keeps the snapshot's default.
	 * @throws \LogicException if the source document has no _id
	 */
	public function copyFrom(MasterDocument $source): static {
		if (empty($source->_id)) {
			throw new \LogicException(static::class . ': source document ' . $source::class . ' must be saved first (no _id)');
		}
		$data = array_intersect_key(get_object_vars($source), array_flip(static::syncedFields()));
		$this->fill($data);
		$this->_id = $source->_id;
		return $this;
	}

	/**
	 * Fields copied from the source: the public properties declared by
	 * the snapshot, minus the framework fields (`_id`, `_deleted`).
	 * Used to decide whether a change on the source must be propagated.
	 * @return string[]
	 */
	public static function syncedFields(): array {
		$fields = [];
		foreach ((new \ReflectionClass(static::class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
			if (!$prop->isStatic() && !in_array($prop->getName(), self::FRAMEWORK_FIELDS, true)) {
				$fields[] = $prop->getName();
			}
		}
		return $fields;
	}

}
