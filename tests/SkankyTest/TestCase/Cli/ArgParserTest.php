<?php

namespace SkankyTest\TestCase\Cli;

use PHPUnit\Framework\TestCase;
use SkankyDev\Cli\Tools\ArgParser;

class ArgParserTest extends TestCase
{
    protected function setUp(): void {
        // Reset Singleton so $options don't bleed between tests
        $ref = new \ReflectionProperty(ArgParser::class, '_instance');
        $ref->setValue(null, null);
    }

    private function parse(array $argv): array {
        // Prepend fake script name (index 0 is always stripped)
        return ArgParser::_pars(array_merge(['craft'], $argv));
    }

    public function testExtractsCommand(): void {
        $result = $this->parse(['queue:work']);
        $this->assertEquals('queue:work', $result['command']);
    }

    public function testLongOptionWithEquals(): void {
        $result = $this->parse(['migrate', '--env=production']);
        $this->assertEquals('migrate',    $result['command']);
        $this->assertEquals('production', $result['--env']);
    }

    public function testLongOptionWithSpace(): void {
        $result = $this->parse(['migrate', '--env', 'production']);
        $this->assertEquals('production', $result['--env']);
    }

    public function testFlagWithoutValueIsTrue(): void {
        $result = $this->parse(['--force']);
        $this->assertTrue($result['--force']);
    }

    public function testShortHelpAlias(): void {
        $result = $this->parse(['-h']);
        $this->assertTrue($result['help']);
    }

    public function testPositionalArgument(): void {
        $result = $this->parse(['crud-maker', 'Module']);
        $this->assertEquals('crud-maker', $result['command']);
        $this->assertEquals('Module', $result[0]);
    }

    public function testNoArguments(): void {
        $result = $this->parse([]);
        $this->assertEmpty($result);
    }

    public function testMultipleOptions(): void {
        $result = $this->parse(['queue:work', '--tries=3', '--sleep=5']);
        $this->assertEquals('queue:work', $result['command']);
        $this->assertEquals('3', $result['--tries']);
        $this->assertEquals('5', $result['--sleep']);
    }
}
