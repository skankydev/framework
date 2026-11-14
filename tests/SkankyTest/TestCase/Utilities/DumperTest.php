<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Dumper;

enum DumperSuit: string
{
    case Hearts = 'h';
}

class DumperPoint
{
    public int $x = 1;
    protected string $label = 'p';
    private array $secret = ['k' => 'v'];
    public ?DumperPoint $self = null;
}

class DumperTest extends TestCase
{
    // ── scalars ───────────────────────────────────────────────────────────────

    public function testScalarsUseTheirCssClass(): void
    {
        $this->assertSame('<span class="debug-keyword">null</span>', Dumper::html(null));
        $this->assertSame('<span class="debug-keyword">true</span>', Dumper::html(true));
        $this->assertSame('<span class="debug-integer">42</span>', Dumper::html(42));
        $this->assertSame('<span class="debug-text">hello</span>', Dumper::html('hello'));
    }

    public function testStringsAreEscaped(): void
    {
        $html = Dumper::html('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // ── arrays & objects ──────────────────────────────────────────────────────

    public function testArrayShowsKeysAndValues(): void
    {
        $html = Dumper::html(['name' => 'bob', 'age' => 30]);

        $this->assertStringContainsString('<span class="debug-source">Array</span>', $html);
        $this->assertStringContainsString('[<span class="debug-var">name</span>] => <span class="debug-text">bob</span>', $html);
        $this->assertStringContainsString('[<span class="debug-var">age</span>] => <span class="debug-integer">30</span>', $html);
    }

    public function testObjectShowsClassAndVisibility(): void
    {
        $html = Dumper::html(new DumperPoint());

        $this->assertStringContainsString(DumperPoint::class, $html);
        $this->assertStringContainsString('>x<', $html);
        $this->assertStringContainsString('label:protected', $html);
        $this->assertStringContainsString('secret:private', $html);
    }

    public function testObjectRecursionIsCut(): void
    {
        $point = new DumperPoint();
        $point->self = $point;

        $this->assertStringContainsString('*RECURSION*', Dumper::html($point));
    }

    public function testEnumAndDateRenderOnOneLine(): void
    {
        $this->assertStringContainsString('Hearts', Dumper::html(DumperSuit::Hearts));
        $this->assertStringContainsString('2026-01-02 03:04:05', Dumper::html(new \DateTime('2026-01-02 03:04:05')));
    }

    // ── limits ────────────────────────────────────────────────────────────────

    public function testItemsLimitCutsLongArrays(): void
    {
        $html = Dumper::html(range(1, 50), ['items' => 5]);

        $this->assertSame(5, substr_count($html, 'debug-var'));
        $this->assertStringContainsString('… +45 more', $html);
    }

    public function testDepthLimitCutsNesting(): void
    {
        $html = Dumper::html(['a' => ['b' => ['c' => 'deep']]], ['depth' => 2]);

        $this->assertStringContainsString('>b<', $html);
        $this->assertStringNotContainsString('deep', $html);
    }

    public function testStringLimitCutsLongStrings(): void
    {
        $html = Dumper::html(str_repeat('a', 100), ['string' => 10]);

        $this->assertStringContainsString('>' . str_repeat('a', 10) . '<', $html);
        $this->assertStringContainsString('… +90 chars', $html);
    }

    public function testHugeGraphStaysSmall(): void
    {
        // The case that used to exhaust memory: thousands of nested entries
        $log = array_fill(0, 60000, [123, 'rotate', 1]);
        $data = array_fill(0, 10, ['replay' => ['log' => $log]]);

        $html = Dumper::html($data);

        $this->assertLessThan(1024 * 1024, strlen($html));
    }

    // ── terminal rendering ────────────────────────────────────────────────────

    public function testCliUsesAnsiColoursAndSameLayout(): void
    {
        $output = Dumper::cli(['name' => 'bob', 'tags' => range(1, 50)], ['items' => 5]);

        $this->assertStringNotContainsString('<span', $output);
        $this->assertStringContainsString("[\033[33mname\033[0m] => \033[92mbob\033[0m", $output);
        $this->assertStringContainsString('… +45 more', $output);
    }

    public function testCliLeavesStringsUnescaped(): void
    {
        $this->assertStringContainsString('<a&b>', Dumper::cli('<a&b>'));
    }
}
