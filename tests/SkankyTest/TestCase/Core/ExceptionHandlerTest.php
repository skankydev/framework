<?php

namespace SkankyTest\TestCase\Core;

use PHPUnit\Framework\TestCase;
use SkankyDev\Core\ExceptionHandler;
use SkankyDev\Utilities\Log;

class ExceptionHandlerTest extends TestCase
{
    private string $logDir;
    private int $errorReporting;

    protected function setUp(): void
    {
        // PHPUnit runs tests with a reduced error_reporting: handleError() would
        // consider every warning masked
        $this->errorReporting = error_reporting(E_ALL);
        $this->logDir = Log::folder();

        if (is_dir($this->logDir)) {
            foreach (glob($this->logDir . '/*.log') as $f) {
                unlink($f);
            }
        }
    }

    protected function tearDown(): void
    {
        error_reporting($this->errorReporting);
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

    public function testHandleDebugBoundsHugeTraceArguments(): void
    {
        // A frame argument holding a huge array must not blow up the debug page
        $thrower   = fn(array $data) => throw new \RuntimeException('Big args');
        $exception = null;
        try {
            $thrower(array_fill(0, 60000, [123, 'rotate', 1]));
        } catch (\RuntimeException $e) {
            $exception = $e;
        }

        ob_start();
        (new ExceptionHandler(true))->handle($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('Big args', $output);
        $this->assertLessThan(1024 * 1024, strlen($output));
    }

    // ── handleError() ─────────────────────────────────────────────────────────

    public function testHandleErrorDebugRendersStyledBlock(): void
    {
        $handler = new ExceptionHandler(true);

        ob_start();
        $handled = $handler->handleError(E_WARNING, 'Undefined variable $scores', '/app/view.php', 12);
        $output = ob_get_clean();

        $this->assertTrue($handled);
        $this->assertStringContainsString('debug-error', $output);
        $this->assertStringContainsString('Warning', $output);
        $this->assertStringContainsString('Undefined variable $scores', $output);
        $this->assertStringContainsString('/app/view.php', $output);
    }

    public function testHandleErrorProductionIsSilentButLogged(): void
    {
        $handler = new ExceptionHandler(false);

        ob_start();
        $handler->handleError(E_WARNING, 'Quiet warning', '/app/view.php', 12);
        $output = ob_get_clean();

        $this->assertSame('', $output);
        $logFile = glob($this->logDir . '/*-error.log');
        $this->assertNotEmpty($logFile);
        $this->assertStringContainsString('Warning: Quiet warning', file_get_contents($logFile[0]));
    }

    public function testHandleErrorLogsTheSameErrorOnce(): void
    {
        $handler = new ExceptionHandler(false);

        $handler->handleError(E_WARNING, 'Looping warning', '/app/view.php', 12);
        $handler->handleError(E_WARNING, 'Looping warning', '/app/view.php', 12);

        $content = file_get_contents(glob($this->logDir . '/*-error.log')[0]);
        $this->assertSame(1, substr_count($content, 'Warning: Looping warning'));
    }

    public function testHandleErrorRespectsSilenceOperator(): void
    {
        $handler  = new ExceptionHandler(true);
        $previous = error_reporting(0);

        ob_start();
        $handled = $handler->handleError(E_WARNING, 'Silenced', '/app/view.php', 12);
        $output = ob_get_clean();
        error_reporting($previous);

        $this->assertFalse($handled);
        $this->assertSame('', $output);
    }

    // ── handle() production mode ──────────────────────────────────────────────

    public function testHandleProductionRendersGenericMessage(): void
    {
        $handler   = new ExceptionHandler(false);
        $exception = new \RuntimeException('Internal details');

        ob_start();
        $handler->handle($exception);
        $output = ob_get_clean();

        $this->assertStringContainsString('An error occurred', $output);
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
