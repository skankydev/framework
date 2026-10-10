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
use SkankyDev\Http\Response;
use SkankyDev\Utilities\RateLimiter;

/**
 * Limite le nombre de requêtes POST par IP sur une action (ex: login, password/lost).
 * Ne s'applique qu'aux requêtes POST — un GET (affichage du form) n'est jamais compté.
 * Ne réinitialise pas le compteur en cas de succès métier (ex: login réussi) : c'est
 * au controller de le faire explicitement via `RateLimiter::clear()` avec la même clé
 * (`{$key}:{ip}`), lui seul sait ce que "succès" veut dire pour son action.
 *
 *     #[Middleware('RateLimit', 'login', 5, TIME_MINUTE * 15)]
 *     public function login(Request $request) { ... }
 */
class RateLimitMiddleware implements MiddlewareInterface {

	public function __construct(
		protected string $key,
		protected int $maxAttempts,
		protected int $decaySeconds
	) {}

	public function handle(Request $request, callable $next): mixed {
		if ($request->method() !== 'POST') {
			return $next($request);
		}

		if (RateLimiter::tooManyAttempts("{$this->key}:{$request->ip()}", $this->maxAttempts, $this->decaySeconds)) {
			if ($request->wantsJson()) {
				return (new Response('', ['ok' => false, 'error' => __('skankydev.http.too_many_attempts')]))
					->status(429);
			}

			$back = $request->header('referer') ?: '/';
			return (new Response())
				->status(302)
				->header('Location', $back)
				->withFlash('error', __('skankydev.http.too_many_attempts'));
		}

		return $next($request);
	}

}
