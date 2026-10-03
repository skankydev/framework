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

namespace SkankyDev\Utilities;

use SkankyDev\Utilities\Traits\ArrayPathable;
/**
 * Static class for get and set variable in session
 */
class Session
{
	use ArrayPathable;
	
	/**
	 * Returns a value from the session using a dot-notation path.
	 * Returns the full session if path is empty.
	 * @param  string $path dot-notation key e.g. `user.name`
	 */
	static function get(string $path = ''): mixed {
		return self::arrayGet($path,$_SESSION);
	}

	/**
	 * Sets a value in the session at a dot-notation path.
	 * Intermediate arrays are created automatically.
	 */
	static function set(string $path, mixed $value): mixed {
		return self::arraySet($path,$value,$_SESSION);
	}

	/**
	 * Deletes a value from the session at a dot-notation path.
	 * @param  string $path dot-notation key e.g. `user.name`
	 */
	static function delete(string $path = ''): void {
		self::arrayDelete($path, $_SESSION);
	}

	/**
	 * Appends a value to an array stored at the given session path.
	 * If the path does not exist yet, an empty array is created first.
	 * Returns false if the existing value at the path is not an array.
	 */
	static function insert(string $path, mixed $value): mixed {
		$tmp = self::get($path) ?? [];

		if (is_array($tmp)) {
			$tmp[] = $value;
			return self::set($path, $tmp);
		}
		return false;
	}

	/**
	 * Returns a session value and immediately deletes it (flash pattern).
	 * @param  string $path dot-notation key e.g. `flash.error`
	 */
	static function getAndClean(string $path = ''): mixed {
		$tmp = self::get($path);
		self::delete($path);
		return $tmp;
	}

	/**
	 * Starts the PHP session.
	 */
	static function start(): void {
		session_start();
	}

	/**
	 * Destroys the PHP session and all its data.
	 */
	static function destroy(): void {
		session_destroy();
	}

	/**
	 * Regenerates the session ID, keeping existing session data.
	 * À appeler à la connexion pour éviter la fixation de session
	 * (un ID de session émis avant l'authentification ne doit plus être valide après).
	 */
	static function regenerate(): void {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
		}
	}

}