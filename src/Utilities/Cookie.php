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
 * Wraps a named browser cookie with dot-notation get/set/delete access.
 * The cookie payload is stored as a JSON-encoded string.
 */
class Cookie {

	use ArrayPathable;

	private array  $data     = [];
	private string $name;
	private int    $time;
	private bool   $secure;
	private bool   $httponly;

	/**
	 * @param string $name     cookie name
	 * @param int    $time     lifetime in seconds from now; 0 = session cookie
	 * @param bool   $secure   transmit over HTTPS only
	 * @param bool   $httponly inaccessible to JavaScript
	 */
	public function __construct(string $name, int $time = 0, bool $secure = false, bool $httponly = true) {
		$this->name     = $name;
		$this->time     = $time ? time() + $time : 0;
		$this->secure   = $secure;
		$this->httponly = $httponly;
		if (isset($_COOKIE[$this->name])) {
			$this->data = json_decode($_COOKIE[$this->name], true) ?? [];
		}
	}

	/** Writes the current data array back to the browser cookie. */
	public function setCookie(): void {
		setcookie($this->name, json_encode($this->data), $this->time, '/', $_SERVER['HTTP_HOST'], $this->secure, $this->httponly);
	}

	/**
	 * Returns a value from the cookie using a dot-notation path.
	 * Returns the full data array when $path is empty.
	 * @param string $path dot-separated key e.g. `user.name`
	 */
	public function get(string $path = ''): mixed {
		return $this->arrayGet($path, $this->data);
	}

	/**
	 * Sets a value in the cookie at a dot-notation path and persists immediately.
	 * @param string $path  dot-separated key e.g. `user.name`
	 * @param mixed  $value the value to store
	 */
	public function set(string $path, mixed $value): void {
		$this->arraySet($path, $value, $this->data);
		$this->setCookie();
	}

	/**
	 * Deletes a value from the cookie at a dot-notation path and persists immediately.
	 * @param string $path dot-separated key e.g. `user.name`
	 */
	public function delete(string $path = ''): void {
		$this->arrayDelete($path, $this->data);
		$this->setCookie();
	}
}