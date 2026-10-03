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

use SkankyDev\Http\Request;

interface MiddlewareInterface {
    /**
     * Handles the request and passes it to the next middleware or controller.
     * @param  Request  $request the current HTTP request
     * @param  callable $next    the next layer in the middleware pipeline
     * @return mixed             the response returned by the pipeline
     */
    public function handle(Request $request, callable $next): mixed;
    
}