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

/**
 * Garde-fou opt-in : n'autorise que les requêtes POST sur l'action ciblée.
 *
 * Le routing étant par convention (pas de distinction GET/POST), une action de
 * mutation (delete, etc.) est sinon atteignable en GET — donc déclenchable par
 * un simple lien ou un <img src>. On l'attache via #[Middleware('PostOnly')].
 * Échec → 405 JSON pour l'AJAX, sinon retour à la page précédente + flash.
 */
class PostOnlyMiddleware implements MiddlewareInterface {

	public function handle(Request $request, callable $next): mixed {
		if ($request->method() === 'POST') {
			return $next($request);
		}

		if ($request->wantsJson()) {
			return (new Response('', ['ok' => false, 'error' => 'Méthode non autorisée']))
				->status(405);
		}

		$back = $request->header('referer') ?: '/';
		return (new Response())
			->status(302)
			->header('Location', $back)
			->withFlash('error', 'Méthode non autorisée.');
	}

}
