<?php

namespace SkankyTest\TestCase\Core;

use PHPUnit\Framework\TestCase;
use SkankyDev\Core\ExceptionHandler;
use SkankyDev\Utilities\Log;

class ExceptionHandlerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = APP_FOLDER . '/logs';

        if (is_dir($this->logDir)) {
            foreach (glob($this->logDir . '/*.log') as $f) {
                unlink($f);
            }
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->logDir)) {
            foreach (glob($this->logDir . '/*.log') as $f) {
                unlink($f);
            }
        }
    }

    // ── handle() debug mode ───────────────────────────────────────────────────

    public function testHandleDebugRendersStackTrace(): void
    {
        $handler   = new ExceptionHandler(true);
        $exception = new \RuntimeException('Test error', 500);

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Test error', $output);
        $this->assertStringContainsString('Stack Trace', $output);
        $this->assertStringContainsString('500', $output);
    }

    public function testHandleDebugLogsError(): void
    {
        $handler   = new ExceptionHandler(true);
        $exception = new \RuntimeException('Logged error');

        ob_start();
        $handler->handle($exception);
        ob_get_clean();

        $logFile = glob($this->logDir . '/*-error.log');
        $this->assertNotEmpty($logFile);
        $content = file_get_contents($logFile[0]);
        $this->assertStringContainsString('Logged error', $content);
    }

    // ── handle() production mode ──────────────────────────────────────────────

    public function testHandleProductionRendersGenericMessage(): void
    {
        $handler   = new ExceptionHandler(false);
        $exception = new \RuntimeException('Internal details');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('erreur', $output);
        $this->assertStringNotContainsString('Internal details', $output);
    }

    public function testHandleProductionLogsError(): void
    {
        $handler   = new ExceptionHandler(false);
        $exception = new \LogicException('Production exception');

        ob_start();
        $handler->handle($exception);
        ob_get_clean();

        $logFile = glob($this->logDir . '/*-error.log');
        $this->assertNotEmpty($logFile);
    }
}
