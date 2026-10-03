<?php

namespace SkankyDev\Http\Middleware;

use SkankyDev\Auth\Auth;
use SkankyDev\Config\Config;
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;

/**
 * Protège une route derrière un keeper d'auth (cf. Auth::keeper()).
 * `#[Middleware(AuthMiddleware::class)]` utilise le keeper par défaut,
 * `#[Middleware(AuthMiddleware::class, 'api')]` en cible un précis.
 * Sans utilisateur résolu : redirige vers `auth.keepers.{keeper}.redirect`
 * si présent et que le client n'attend pas du JSON, sinon 401 JSON.
 */
class AuthMiddleware implements MiddlewareInterface {

	public function __construct(private ?string $keeper = null) {}

	public function handle(Request $request, callable $next): mixed {
		if (Auth::keeper($this->keeper)->check()) {
			return $next($request);
		}

		$name     = $this->keeper ?? Config::get('auth.default');
		$redirect = Config::get("auth.keepers.{$name}.redirect");

		if ($redirect && !$request->wantsJson()) {
			return (new Response())
				->status(302)
				->header('Location', $redirect)
				->withFlash('error', 'Merci de vous connecter.');
		}

		return (new Response('', ['ok' => false, 'error' => 'Non authentifié']))->status(401);
	}

}
