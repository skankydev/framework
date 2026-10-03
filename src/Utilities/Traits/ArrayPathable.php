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


namespace SkankyDev\Utilities\Traits;

/**
 * Provides dot-notation read/write/delete access to nested arrays.
 * Example: `'foo.bar'` maps to `$array['foo']['bar']`.
 */
trait ArrayPathable {

	/** Fallback pointer used when none is provided externally. */
	static array $myArrayPathable = [];

	/**
	 * Reads a value from a nested array using a dot-notation path.
	 * Returns the entire array when $path is empty.
	 * Returns null if any key along the path is missing.
	 * @param  string $path    dot-separated key e.g. `user.name`
	 * @param  array  &$pointer the array to read from
	 * @return mixed
	 */
	static function arrayGet(string $path, array &$pointer): mixed {
		if (strlen($path) === 0) {
			return $pointer;
		}
		$keys   = explode('.', $path);
		$retour = $pointer;
		foreach ($keys as $key) {
			if (isset($retour[$key])) {
				$retour = $retour[$key];
			} else {
				return null;
			}
		}
		return $retour;
	}

	/**
	 * Sets a value in a nested array at the given dot-notation path.
	 * Intermediate arrays are created automatically.
	 * @param  string $path     dot-separated key e.g. `user.name`
	 * @param  mixed  $value    the value to set
	 * @param  array  &$pointer the array to write to
	 * @return bool             always true
	 */
	static function arraySet(string $path, mixed $value, array &$pointer): bool {
		$aPath = explode('.', $path);

		if (count($aPath) > 1) {
			$path = substr(strstr($path, '.'), 1);
			if (!isset($pointer[$aPath[0]]) || !is_array($pointer[$aPath[0]])) {
				$pointer[$aPath[0]] = [];
			}
			return self::arraySet($path, $value, $pointer[$aPath[0]]);
		}

		$pointer[$aPath[0]] = $value;
		return true;
	}

	/**
	 * Deletes a value from a nested array at the given dot-notation path.
	 * Returns null if an intermediate key does not exist.
	 * @param  string $path     dot-separated key e.g. `user.name`
	 * @param  array  &$pointer the array to modify
	 * @return bool|null        true on success, null if path not found
	 */
	static function arrayDelete(string $path, array &$pointer): bool|null {
		$aPath = explode('.', $path);

		if (count($aPath) > 1) {
			$path = substr(strstr($path, '.'), 1);
			if (!isset($pointer[$aPath[0]])) {
				return null;
			}
			return self::arrayDelete($path, $pointer[$aPath[0]]);
		}

		unset($pointer[$aPath[0]]);
		return true;
	}
}