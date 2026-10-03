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

namespace SkankyDev\Http\Middleware\Attribute;

use Attribute;

/**
 * Declares a middleware attached to a controller or one of its actions.
 *
 * Because routing is convention-based (no explicit route to hang middlewares on),
 * the controller/action is the stable target — so the attribute is read there at
 * dispatch time by MiddlewareManager.
 *
 * - On a class  → applies to every action of the controller.
 * - On a method → applies to that action only (in addition to class-level ones).
 *
 * One attribute = one middleware. The arguments after the class name are passed
 * to the middleware constructor (resolved through MasterFactory):
 *
 *   #[Middleware(AuthMiddleware::class)]                 // no argument
 *   #[Middleware(PermissionMiddleware::class, 'edit')]   // 'edit' → constructor
 *
 * Repeatable, so stack it to attach several middlewares:
 *   #[Middleware(AuthMiddleware::class)]
 *   #[Middleware(PermissionMiddleware::class, 'edit')]
 *
 * The class is either a fully qualified middleware class name or an alias
 * registered in the `class.middlewares` config map.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware {

	/** Middleware class name (or config alias) to run. */
	public string $middleware;

	/** Positional arguments forwarded to the middleware constructor. */
	public array $args;

	public function __construct(string $middleware, mixed ...$args) {
		$this->middleware = $middleware;
		$this->args = $args;
	}

}
