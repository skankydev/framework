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
 * Base class of every event emitted with EventManager::_emit().
 *
 * An event is a plain object carrying the context of what just happened (the
 * comment, its author...): put it in public properties, readonly or not. It's
 * never persisted, it only lives for the duration of the emit.
 *
 * Its name is its class: listeners subscribe to `CommentCreated::class`.
 */
abstract class MasterEvent {

	/** Name the listeners subscribe to: the event class, unless a subclass says otherwise. */
	public function getName(): string {
		return static::class;
	}
}
