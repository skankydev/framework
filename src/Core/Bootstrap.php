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

namespace SkankyDev\Core;

use SkankyDev\Config\Config;

/**
 * Démarrage du framework, commun au web et au CLI : à appeler juste après
 * l'autoload, avant le bootstrap de l'application (config/bootstrap.php).
 *
 *   require 'vendor/autoload.php';
 *   \SkankyDev\Core\Bootstrap::boot();
 *   require 'config/bootstrap.php';
 *
 * Ce qui est propre au projet (choix de la langue, services...) reste dans
 * config/bootstrap.php.
 */
class Bootstrap {

	private static bool $booted = false;

	/**
	 * Charge le .env, assemble la config, règle la timezone et l'encodage.
	 * Le .env passe avant la config : master.config.php lit getenv().
	 * Sans effet si déjà appelé.
	 */
	static function boot(): void {
		if (self::$booted) {
			return;
		}
		self::$booted = true;

		self::loadEnv(APP_FOLDER . DS . '.env');
		Config::initConf();
		date_default_timezone_set(Config::get('timeHelper.timezone') ?? 'UTC');
		mb_internal_encoding('UTF-8');
	}

	/**
	 * Charge un fichier .env (`CLE=valeur` par ligne, `#` pour commenter)
	 * dans $_ENV et getenv(). Ne fait rien si le fichier n'existe pas.
	 */
	static function loadEnv(string $envFile): void {
		if (!file_exists($envFile)) {
			return;
		}
		foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
			if (str_starts_with(trim($line), '#')) {
				continue;
			}
			[$key, $value] = explode('=', $line, 2) + [1 => ''];
			$_ENV[trim($key)] = trim($value);
			putenv(trim($key) . '=' . trim($value));
		}
	}
}
