<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Traits\CliMessage;

// Helper class that uses the trait
class CliMessageTestHelper {
    use CliMessage;
}

class CliMessageTest extends TestCase
{
    private CliMessageTestHelper $cli;

    protected function setUp(): void
    {
        $this->cli = new CliMessageTestHelper();
    }

    public function testInfoOutputsMessage(): void
    {
        ob_start();
        $this->cli->info('hello info');
        $output = ob_get_clean();
        $this->assertStringContainsString('hello info', $output);
    }

    public function testInfoTOutputsIndentedMessage(): void
    {
        ob_start();
        $this->cli->infoT('indented');
        $output = ob_get_clean();
        $this->assertStringContainsString('indented', $output);
    }

    public function testErrorOutputsMessage(): void
    {
        ob_start();
        $this->cli->error('something failed');
        $output = ob_get_clean();
        $this->assertStringContainsString('something failed', $output);
    }

    public function testSuccessOutputsMessage(): void
    {
        ob_start();
        $this->cli->success('done');
        $output = ob_get_clean();
        $this->assertStringContainsString('done', $output);
    }

    public function testWarningOutputsMessage(): void
    {
        ob_start();
        $this->cli->warning('be careful');
        $output = ob_get_clean();
        $this->assertStringContainsString('be careful', $output);
    }

    public function testTextOutputsMessage(): void
    {
        ob_start();
        $this->cli->text('plain text');
        $output = ob_get_clean();
        $this->assertStringContainsString('plain text', $output);
    }

    public function testLineOutputsSeparator(): void
    {
        ob_start();
        $this->cli->line();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }

    public function testArrayOutputsStructuredData(): void
    {
        ob_start();
        $this->cli->array(['key' => 'value', 'num' => 42, 'flag' => true]);
        $output = ob_get_clean();
        $this->assertStringContainsString('key', $output);
        $this->assertStringContainsString('value', $output);
    }

    public function testArrayWithNestedArray(): void
    {
        ob_start();
        $this->cli->array(['nested' => ['a', 'b']]);
        $output = ob_get_clean();
        $this->assertStringContainsString('nested', $output);
    }

    public function testArrayWithNullAndObject(): void
    {
        ob_start();
        $this->cli->array(['nullable' => null, 'obj' => new \stdClass()]);
        $output = ob_get_clean();
        $this->assertStringContainsString('null', $output);
        $this->assertStringContainsString('stdClass', $output);
    }
}
