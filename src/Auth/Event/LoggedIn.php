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
 * A user was just logged in: by a form (attempt() / login()), or restored from
 * the remember-me cookie at the start of a request ($viaCookie).
 */
class LoggedIn extends MasterEvent {

	/**
	 * @param object $user      the provider document (e.g. App\Model\Document\User)
	 * @param bool   $remember  a remember-me cookie was set (or used)
	 * @param bool   $viaCookie restored from the remember-me cookie, no form involved
	 */
	public function __construct(
		public readonly object $user,
		public readonly bool $remember = false,
		public readonly bool $viaCookie = false,
	) {}
}
