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

use SkankyDev\Config\Config;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Exception\EventException;
use SkankyDev\Utilities\Traits\Singleton;

/**
 * Event dispatcher (Observer pattern): the code that emits an event doesn't
 * know who listens to it.
 *
 * ```php
 * EventManager::_emit(new CommentCreated($comment));
 * ```
 *
 * Listeners come from two places, called in this order:
 * - the `listeners` config (`[TrophyListener::class => true]`), read on the first
 *   emit only; each listener is built through MasterFactory when one of its events
 *   is emitted, then reused for the rest of the request;
 * - listen(), to plug a callable by hand (config/bootstrap.php, a test...).
 *
 * Nothing is caught: an exception thrown by a listener goes up to the caller,
 * like any other (a NotFoundException with code 404 gives a 404 page).
 */
class EventManager {

	use Singleton;

	/** @var array<string, list<array{class: string, method: string}>> listeners from the config, by event */
	private array $subscribed = [];
	/** @var array<string, list<callable>> listeners plugged with listen(), by event */
	private array $manual = [];
	/** @var array<string, object> listeners already built, by class */
	private array $instances = [];
	private bool $loaded = false;

	/**
	 * Plugs a callable on an event. It receives the event as its only argument.
	 * @param string $event event class (or the name returned by its getName())
	 */
	public function listen(string $event, callable $listener): void {
		$this->manual[$event][] = $listener;
	}

	/**
	 * Calls every listener of the event, in order, then gives the event back
	 * (a listener may have filled it).
	 * @throws EventException when a listener of the config is badly declared
	 */
	public function emit(MasterEvent $event): MasterEvent {
		$this->load();
		$name = $event->getName();

		foreach ($this->subscribed[$name] ?? [] as $listener) {
			$this->instance($listener['class'])->{$listener['method']}($event);
		}
		foreach ($this->manual[$name] ?? [] as $listener) {
			$listener($event);
		}

		return $event;
	}

	/** True when at least one listener (config or listen()) waits for this event. */
	public function hasListeners(string $event): bool {
		$this->load();
		return !empty($this->subscribed[$event]) || !empty($this->manual[$event]);
	}

	/**
	 * Reads the `subscribe()` of every enabled listener of the config, once.
	 * Only the class and method names are kept: nothing is built here.
	 */
	private function load(): void {
		if ($this->loaded) {
			return;
		}
		$this->loaded = true;

		$listeners = empty(Config::$conf) ? [] : (array) Config::get('listeners');
		foreach ($listeners as $class => $enabled) {
			if (!$enabled) {
				continue;
			}
			if (!class_exists($class)) {
				throw new EventException("Listener {$class} not found: check the `listeners` config.");
			}
			if (!is_subclass_of($class, ListenerInterface::class)) {
				throw new EventException("Listener {$class} must implement " . ListenerInterface::class . '.');
			}
			foreach ($class::subscribe() as $event => $methods) {
				foreach ((array) $methods as $method) {
					if (!method_exists($class, $method)) {
						throw new EventException("Listener {$class} subscribes {$event} to {$method}(), which doesn't exist.");
					}
					$this->subscribed[$event][] = ['class' => $class, 'method' => $method];
				}
			}
		}
	}

	/** The listener's instance, built on first use only. */
	private function instance(string $class): object {
		return $this->instances[$class] ??= MasterFactory::_make($class);
	}
}
