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

namespace SkankyDev\Event;

/**
 * A class listening to events, declared in the `listeners` config:
 * `'listeners' => [TrophyListener::class => true]` (false to switch it off).
 *
 * subscribe() is static so the EventManager can read it without building the
 * listener: the instance is only created (through MasterFactory, once per
 * request) when one of its events is actually emitted.
 */
interface ListenerInterface {

	/**
	 * Events this listener handles, and the method(s) called for each one.
	 * Every method receives the event as its only argument.
	 * @return array<class-string<MasterEvent>, string|string[]>
	 *         e.g. `[CommentCreated::class => 'onComment', PageNotFound::class => ['onLost', 'log']]`
	 */
	public static function subscribe(): array;
}
