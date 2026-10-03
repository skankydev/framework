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

namespace SkankyDev\Http\Middleware;

use SkankyDev\Http\Middleware\MiddlewareInterface;
use SkankyDev\Http\Request;
use SkankyDev\Utilities\Session;

class SessionMiddleware  implements MiddlewareInterface{

	/**
	 * Starts the session then passes the request to the next middleware.
	 */
	public function handle(Request $request, callable $next): mixed {
		Session::start();
		return $next($request);
	}

}
