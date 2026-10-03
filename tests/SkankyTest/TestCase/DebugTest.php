<?php

namespace SkankyTest\TestCase;

use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
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

    public function testDumpOutputsPlainText(): void {
        // dump() est la version terminal-friendly : du texte brut (var_dump),
        // pas le HTML de debug() — illisible une fois dumpé tel quel dans un
        // terminal. C'est la différence voulue entre les deux fonctions.
        ob_start();
        dump('test-value');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<div class="debug-message">', $output);
        $this->assertStringContainsString('test-value', $output);
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
