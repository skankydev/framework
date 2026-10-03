<?php

namespace TestApp\View\Part;

use SkankyDev\View\Part\MasterPart;

class GreetPart extends MasterPart {

    public function data(array $options): array {
        return ['greeting' => 'Salut ' . ($options['name'] ?? '')];
    }

}
