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

namespace SkankyDev\Auth\Event;

use SkankyDev\Event\MasterEvent;

/**
 * A login attempt failed (unknown login, wrong password, missing field).
 * Never carries the password.
 */
class LoginFailed extends MasterEvent {

	/**
	 * @param string      $identifier name of the login field (config `identifier`, e.g. `email`)
	 * @param string|null $login      the value typed for it, null when empty
	 */
	public function __construct(
		public readonly string $identifier,
		public readonly ?string $login,
	) {}
}
