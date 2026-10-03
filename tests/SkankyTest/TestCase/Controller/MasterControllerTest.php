<?php

namespace SkankyTest\TestCase\Controller;

use PHPUnit\Framework\TestCase;
use SkankyDev\Controller\MasterController;

class ConcreteController extends MasterController {}

class MasterControllerTest extends TestCase
{
    public function testInstantiates(): void
    {
        $controller = new MasterController();
        $this->assertInstanceOf(MasterController::class, $controller);
    }

    public function testCanBeExtended(): void
    {
        $controller = new ConcreteController();
        $this->assertInstanceOf(MasterController::class, $controller);
    }
}
