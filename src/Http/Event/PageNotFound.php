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

namespace SkankyDev\Http\Event;

use SkankyDev\Event\MasterEvent;
use Throwable;

/**
 * A 404 is about to be sent: notFound() called by a controller, or a
 * NotFoundException (code 404) caught by Application (in debug too).
 */
class PageNotFound extends MasterEvent {

	/**
	 * @param string         $uri       the requested URI
	 * @param Throwable|null $exception the NotFoundException, when there is one
	 */
	public function __construct(
		public readonly string $uri,
		public readonly ?Throwable $exception = null,
	) {}
}
