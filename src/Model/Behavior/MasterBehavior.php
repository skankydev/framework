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

namespace SkankyDev\Model\Behavior;


/**
 * Base class for collection behaviors.
 * Behaviors are hooks called automatically by MasterCollection at key points
 * in the document lifecycle (insert, update, entity creation).
 * Override only the hooks you need in concrete behavior classes.
 */
class MasterBehavior {

	function __construct() {}

	/** Called before a document is inserted. Receives the document to mutate. */
	public function beforeInsert(object $data, mixed $entity = null): void {}

	/** Called after a document is inserted. */
	public function afterInsert(object $data, mixed $entity = null): void {}

	/** Called before a document is updated. Receives the document to mutate. */
	public function beforeUpdate(object $data, mixed $entity = null): void {}

	/** Called after a document is updated. */
	public function afterUpdate(object $data, mixed $entity = null): void {}

	/** Called before an entity is created from raw data. */
	public function beforeCreateEntity(mixed $data, mixed $entity = null): void {}

	/** Called after an entity is created from raw data. */
	public function afterCreateEntity(mixed $data, mixed $entity = null): void {}

}
