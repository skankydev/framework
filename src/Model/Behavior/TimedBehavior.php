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

use SkankyDev\Model\Behavior\MasterBehavior;
use MongoDB\BSON\UTCDateTime as MongoDate;
use DateTime;

/**
 * Automatically manages `created_at` and `updated_at` timestamps on documents.
 * Sets both on insert, updates only `updated_at` on update.
 */
class TimedBehavior extends MasterBehavior {

	public function __construct() {}

	/** Sets created_at and updated_at to now before insert. */
	public function beforeInsert(object $data, mixed $entity = null): void {
		$data->created_at = new DateTime;
		$data->updated_at = new DateTime;
	}

	/** Updates updated_at to now before update. */
	public function beforeUpdate(object $data, mixed $entity = null): void {
		$data->updated_at = new DateTime;
	}

}
