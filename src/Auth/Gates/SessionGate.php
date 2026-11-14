<?php

namespace SkankyDev\Auth\Gates;

use SkankyDev\Auth\Event\LoggedIn;
use SkankyDev\Auth\Event\LoggedOut;
use SkankyDev\Auth\Event\LoginFailed;
use SkankyDev\Event\EventManager;
use SkankyDev\Http\Request;
use SkankyDev\Utilities\Hash;
use SkankyDev\Utilities\Session;
use SkankyDev\Utilities\Token;

/**
 * Auth par identifiant + mot de passe, portée par la session PHP.
 * Remember-me optionnel (config `remember` => true) : un Token est stocké
 * hashé sur le document (propriété `remember_token`), sa valeur en clair
 * part dans un cookie côté client. Le token est régénéré à chaque usage du
 * cookie (rotation), pour limiter la fenêtre de rejeu si le cookie fuite.
 *
 * Événements : LoggedIn (login(), attempt() réussi, session restaurée par le
 * cookie), LoginFailed (attempt() raté), LoggedOut (logout() d'un connecté).
 */
class SessionGate extends MasterGate {

	private const SESSION_KEY = 'auth.user_id';
	private const COOKIE_NAME = 'auth.remember_token';

	public function attempt(array $credentials): bool {
		$identifier = $this->config['identifier'] ?? 'email';
		$login    = $credentials[$identifier] ?? null;
		$password = $credentials['password'] ?? null;

		if (empty($login) || empty($password)) {
			EventManager::_emit(new LoginFailed($identifier, empty($login) ? null : (string) $login));
			return false;
		}

		$user = $this->provider()->findOne([$identifier => $login]);
		if (!$user || empty($user->password) || !Hash::check($password, $user->password)) {
			EventManager::_emit(new LoginFailed($identifier, (string) $login));
			return false;
		}

		$this->login($user, !empty($credentials['remember']));
		return true;
	}

	public function login(object $user, bool $remember = false): void {
		Session::regenerate();
		Session::set(self::SESSION_KEY, (string) $user->_id);

		$remember = $remember && !empty($this->config['remember']);
		if ($remember) {
			$this->remember($user);
		}

		parent::login($user);
		EventManager::_emit(new LoggedIn($user, $remember));
	}

	public function logout(): void {
		$user = $this->user();
		if ($user !== null && $user->remember_token !== null) {
			$user->remember_token = null;
			$this->provider()->saveSneaky($user);
		}
		Session::delete(self::SESSION_KEY);
		$this->forgetCookie();
		parent::logout();
		if ($user !== null) {
			EventManager::_emit(new LoggedOut($user));
		}
	}

	protected function resolve(): ?object {
		$id = Session::get(self::SESSION_KEY);
		if ($id) {
			return $this->provider()->findById($id);
		}

		if (!empty($this->config['remember'])) {
			return $this->resolveFromRememberCookie();
		}

		return null;
	}

	/** Restaure la session à partir du cookie remember-me si le token correspond. */
	private function resolveFromRememberCookie(): ?object {
		$cookie = Request::getInstance()->cookie(self::COOKIE_NAME);
		if (!$cookie || !str_contains($cookie, '|')) {
			return null;
		}

		[$userId, $plainToken] = explode('|', $cookie, 2);
		$user = $this->provider()->findById($userId);

		if (!$user || !($user->remember_token instanceof Token)) {
			return null;
		}
		if (!$user->remember_token->checkTime(TIME_MONTH)) {
			return null;
		}
		if (!hash_equals($user->remember_token->value, Hash::hashToken($plainToken))) {
			return null;
		}

		Session::set(self::SESSION_KEY, (string) $user->_id);
		$this->remember($user);
		$this->pendingEvent = new LoggedIn($user, remember: true, viaCookie: true);
		return $user;
	}

	/** Génère un nouveau token remember-me : hash stocké en base, valeur en clair en cookie. */
	private function remember(object $user): void {
		$plain = new Token();
		$user->remember_token = new Token(['value' => Hash::hashToken($plain->value), 'time' => $plain->time]);
		$this->provider()->saveSneaky($user);

		setcookie(self::COOKIE_NAME, $user->_id . '|' . $plain->value, [
			'expires'  => time() + TIME_MONTH,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}

	private function forgetCookie(): void {
		setcookie(self::COOKIE_NAME, '', [
			'expires'  => time() - TIME_HOUR,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
		]);
	}

}
