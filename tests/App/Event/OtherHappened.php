<?php

namespace TestApp\Event;

use SkankyDev\Event\MasterEvent;

class OtherHappened extends MasterEvent {

    public array $calls = [];
}
