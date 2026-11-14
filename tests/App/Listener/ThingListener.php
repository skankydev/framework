<?php

namespace TestApp\Listener;

use SkankyDev\Event\ListenerInterface;
use TestApp\Event\OtherHappened;
use TestApp\Event\ThingHappened;

class ThingListener implements ListenerInterface {

    public static int $built = 0;

    public function __construct() {
        self::$built++;
    }

    public static function subscribe(): array {
        return [
            ThingHappened::class => ['first', 'second'],
            OtherHappened::class => 'other',
        ];
    }

    public function first(ThingHappened $event): void {
        $event->calls[] = 'first:' . $event->what;
    }

    public function second(ThingHappened $event): void {
        $event->calls[] = 'second';
    }

    public function other(OtherHappened $event): void {
        $event->calls[] = 'other';
    }
}
