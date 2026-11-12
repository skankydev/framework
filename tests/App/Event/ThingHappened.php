<?php

namespace TestApp\Event;

use SkankyDev\Event\MasterEvent;

class ThingHappened extends MasterEvent {

    /** Filled by the listeners, in call order. */
    public array $calls = [];

    public function __construct(public readonly string $what = 'thing') {}
}
