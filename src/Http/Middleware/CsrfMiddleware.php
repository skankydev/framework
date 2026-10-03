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
use SkankyDev\Http\Response;
use SkankyDev\Utilities\Session;

/**
 * Protection CSRF (synchronizer token, 1 token par session).
 *
 * Sur les méthodes non-sûres (POST/PUT/PATCH/DELETE), compare le token soumis
 * (champ `_token` des forms, ou header `X-CSRF-Token` pour l'AJAX) au token stocké
 * en session. Échec → 419 JSON pour l'AJAX, sinon retour à la page précédente + flash.
 * Le token de session est généré par csrf_token() / csrf_field().
 */
class CsrfMiddleware implements MiddlewareInterface {

	private const UNSAFE = ['POST', 'PUT', 'PATCH', 'DELETE'];

	public function handle(Request $request, callable $next): mixed {
		if (!in_array($request->method(), self::UNSAFE, true)) {
			return $next($request);
		}

		$sessionToken = Session::get('csrf_token');
		$submitted    = $request->input('_token') ?? $request->header('x-csrf-token');

		if (is_string($sessionToken) && is_string($submitted) && hash_equals($sessionToken, $submitted)) {
			return $next($request);
		}

		// AJAX → JSON 419 ; navigation classique → retour arrière avec flash.
		if ($request->wantsJson()) {
			return (new Response('', ['ok' => false, 'error' => 'Token CSRF invalide ou session expirée']))
				->status(419);
		}

		$back = $request->header('referer') ?: '/';
		return (new Response())
			->status(302)
			->header('Location', $back)
			->withFlash('error', 'Session expirée, merci de réessayer.');
	}

}
