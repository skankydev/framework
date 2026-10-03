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

namespace SkankyDev\Model;

use SkankyDev\Model\Document\RateLimitEntry;
use SkankyDev\Utilities\Traits\Singleton;

class RateLimitCollection extends MasterCollection {

	use Singleton;

	protected string $collectionName = 'rate_limits';
	protected string $documentClass = RateLimitEntry::class;

	public function getIndexes(): array {
		return [
			['key' => ['attempts_key' => 1], 'unique' => true, 'name' => 'attempts_key_unique'],
			['key' => ['expires_at' => 1], 'expireAfterSeconds' => 0, 'name' => 'expires_at_ttl'],
		];
	}

}
