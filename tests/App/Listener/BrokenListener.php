<?php

namespace TestApp\Listener;

use SkankyDev\Event\ListenerInterface;
use TestApp\Event\ThingHappened;

/** Subscribes to a method that doesn't exist. */
class BrokenListener implements ListenerInterface {

    public static function subscribe(): array {
        return [ThingHappened::class => 'missing'];
    }
}
