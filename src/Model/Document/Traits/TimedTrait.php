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

namespace SkankyDev\Model\Document\Traits;

use DateTime;

/**
 * Companion trait of TimedBehavior.
 * Declares the timestamp properties the behavior manages so they live as real
 * typed properties on the document — no dynamic properties required.
 *
 * A document opts into the behavior simply by using this trait; MasterCollection
 * resolves the matching behavior by convention:
 * `...\Model\Document\Traits\TimedTrait` -> `...\Model\Behavior\TimedBehavior`.
 */
trait TimedTrait {

	public ?DateTime $created_at = null;
	public ?DateTime $updated_at = null;

}
