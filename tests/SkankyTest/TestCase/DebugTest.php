<?php

namespace SkankyTest\TestCase;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Dumper;

class DebugTest extends TestCase
{
    protected function setUp(): void
    {
        // PHPUnit runs in CLI: force the page rendering, the terminal one has its own tests
        Dumper::forceMode('html');
    }

    protected function tearDown(): void
    {
        Dumper::forceMode(null);
    }

    // ── debug() in a terminal ─────────────────────────────────────────────────

    public function testDebugDetectsCliFromSapi(): void {
        Dumper::forceMode(null);

        $this->assertTrue(Dumper::isCli());
    }

    public function testDebugCliOutputsAnsiNotHtml(): void {
        Dumper::forceMode('cli');

        ob_start();
        debug(['key' => 'val']);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<div', $output);
        $this->assertStringNotContainsString('<span', $output);
        $this->assertStringContainsString("\033[", $output);
        $this->assertStringContainsString('key', $output);
        $this->assertStringContainsString('val', $output);
        $this->assertStringContainsString('debug : ', $output);
    }

    public function testDebugCliDoesNotEscapeHtml(): void {
        Dumper::forceMode('cli');

        ob_start();
        debug('<b>bold</b>', 'label');
        $output = ob_get_clean();

        $this->assertStringContainsString('<b>bold</b>', $output);
    }

    // ── debug() ───────────────────────────────────────────────────────────────

    public function testDebugOutputsDiv(): void {
        ob_start();
        debug('hello');
        $html = ob_get_clean();

        $this->assertStringContainsString('<div class="debug-message">', $html);
        $this->assertStringContainsString('</div>', $html);
    }

    public function testDebugOutputsValue(): void {
        ob_start();
        debug('hello world');
        $html = ob_get_clean();

        $this->assertStringContainsString('hello world', $html);
    }

    public function testDebugWithCustomMessage(): void {
        ob_start();
        debug(['key' => 'val'], 'My label');
        $html = ob_get_clean();

        $this->assertStringContainsString('My label', $html);
        $this->assertStringContainsString('val', $html);
    }

    public function testDebugWithArray(): void {
        ob_start();
        debug(['a' => 1, 'b' => 2]);
        $html = ob_get_clean();

        $this->assertStringContainsString('Array', $html);
        $this->assertStringContainsString('<pre>', $html);
    }

    public function testDebugWithBoolTrue(): void {
        ob_start();
        debug(true);
        $html = ob_get_clean();

        $this->assertStringContainsString('true', $html);
    }

    public function testDebugWithBoolFalse(): void {
        ob_start();
        debug(false);
        $html = ob_get_clean();

        $this->assertStringContainsString('false', $html);
    }

    public function testDebugWithInteger(): void {
        ob_start();
        debug(42);
        $html = ob_get_clean();

        $this->assertStringContainsString('42', $html);
    }

    // ── dump() ────────────────────────────────────────────────────────────────

    public function testDumpRendersLikeDebug(): void {
        // dump() is debug() behind an optional label: same rendering, picked the
        // same way (HTML block on a page, ANSI text in a terminal).
        ob_start();
        dump('test-value');
        $html = ob_get_clean();

        $this->assertStringContainsString('<div class="debug-message">', $html);
        $this->assertStringContainsString('test-value', $html);

        Dumper::forceMode('cli');
        ob_start();
        dump('test-value');
        $text = ob_get_clean();

        $this->assertStringNotContainsString('<div', $text);
        $this->assertStringContainsString('test-value', $text);
    }

    public function testDumpHeaderPointsAtItsOwnCaller(): void {
        // Not at the debug() call inside dump() itself
        ob_start();
        dump('test-value');
        $html = ob_get_clean();

        $this->assertStringContainsString('DebugTest', $html);
        $this->assertStringContainsString(__FUNCTION__, $html);
        $this->assertStringNotContainsString('debug.php', $html);
    }

    public function testDumpWithMessagePrefixesOutput(): void {
        ob_start();
        dump('test-value', 'My label');
        $output = ob_get_clean();

        $this->assertStringContainsString('My label', $output);
        $this->assertStringContainsString('test-value', $output);
    }

    public function testDumpDoesNotDie(): void {
        ob_start();
        dump('alive');
        ob_get_clean();

        // If we reach this assertion, dump() did not call die()
        $this->assertTrue(true);
    }
}
