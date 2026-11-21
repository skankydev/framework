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
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;

/**
 * The response has been sent to the client: the place for work the visitor
 * shouldn't wait for (stats, logs...).
 *
 * Emitted by Application once send() is done. Under PHP-FPM the connection is
 * already closed (fastcgi_finish_request()), so a listener costs the visitor nothing;
 * elsewhere (CLI, built-in server) it simply runs before the end of the script.
 * The session is closed too: a listener can read $_SESSION but its writes are lost.
 *
 * Not emitted for redirections (send() exits on a 3xx) nor for error pages.
 * A listener's exception is logged, never shown: there is no page left to show it on.
 */
class ResponseSent extends MasterEvent {

	public function __construct(
		public readonly Request $request,
		public readonly Response $response,
	) {}
}
