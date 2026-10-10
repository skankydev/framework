<?php

namespace SkankyDev\Auth;

use SkankyDev\Auth\Gates\MasterGate;
use SkankyDev\Config\Config;

/**
 * Façade statique par-dessus les gates. `Auth::keeper('web')` résout et met
 * en cache la gate configurée pour le slot `web` (cf. config `auth.keepers`
 * + `class.gates`) ; `Auth::keeper()` sans argument utilise `auth.default`.
 * Les autres méthodes délèguent au keeper par défaut.
 */
class Auth {

	private static array $keepers = [];

	public static function keeper(?string $name = null): MasterGate {
		$name ??= Config::get('auth.default');

		if (!isset(self::$keepers[$name])) {
			self::$keepers[$name] = self::makeKeeper($name);
		}

		return self::$keepers[$name];
	}

	private static function makeKeeper(string $name): MasterGate {
		$config = Config::get("auth.keepers.{$name}");
		if (!is_array($config)) {
			throw new \Exception("Unknown auth keeper: \"{$name}\"", 500);
		}

		$gateClass = Config::get('class.gates')[$config['gate']] ?? null;
		if ($gateClass === null || !class_exists($gateClass)) {
			throw new \Exception("Unknown auth gate: \"{$config['gate']}\"", 500);
		}

		$providerClass = Config::get('auth.providers')[$config['provider']] ?? null;
		if ($providerClass === null) {
			throw new \Exception("Unknown auth provider: \"{$config['provider']}\"", 500);
		}

		return new $gateClass($config, $providerClass);
	}

	public static function attempt(array $credentials): bool {
		return self::keeper()->attempt($credentials);
	}

	public static function login(object $user): void {
		self::keeper()->login($user);
	}

	public static function logout(): void {
		self::keeper()->logout();
	}

	public static function user(): ?object {
		return self::keeper()->user();
	}

	public static function check(): bool {
		return self::keeper()->check();
	}

	public static function id(): mixed {
		return self::keeper()->id();
	}

}
