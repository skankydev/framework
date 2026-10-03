<?php

namespace SkankyTest\TestCase\Command;

use PHPUnit\Framework\TestCase;
use SkankyDev\Command\MasterCommand;

class ConcreteCommand extends MasterCommand {
    static protected string $signature = 'test:command';
    static protected string $help      = 'A test command';

    public function run(array $arg = []): void {}
}

class MasterCommandTest extends TestCase
{
    public function testGetInfoReturnsCorrectMetadata(): void {
        $info = ConcreteCommand::getInfo();

        $this->assertArrayHasKey('class',     $info);
        $this->assertArrayHasKey('signature', $info);
        $this->assertArrayHasKey('help',      $info);

        $this->assertEquals('test:command', $info['signature']);
        $this->assertEquals('A test command', $info['help']);
        $this->assertEquals(ConcreteCommand::class, $info['class']);
    }

    public function testRunIsCallable(): void {
        $cmd = new ConcreteCommand();
        // run() must not throw
        $cmd->run([]);
        $this->assertTrue(true);
    }
}
