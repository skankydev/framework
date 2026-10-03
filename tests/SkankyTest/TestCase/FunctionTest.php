<?php

namespace SkankyTest\TestCase;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Response;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;

class FunctionTest extends TestCase
{
    protected function setUp(): void {
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
        $ref = new \ReflectionProperty(UrlBuilder::class, '_instance');
        $ref->setValue(null, null);
        Router::_findCurrentRoute('/module/index');
        $_SESSION = [];
    }

    // ── e() ──────────────────────────────────────────────────────────────────

    public function testEscapesHtml(): void {
        $this->assertEquals('&lt;script&gt;', e('<script>'));
        $this->assertEquals('&lt;b&gt;bold&lt;/b&gt;', e('<b>bold</b>'));
    }

    public function testEscapesQuotes(): void {
        $this->assertEquals('&quot;hello&quot;', e('"hello"'));
        $this->assertEquals('&#039;test&#039;', e("'test'"));
    }

    public function testEscapesNull(): void {
        $this->assertEquals('', e(null));
    }

    public function testPlainStringUnchanged(): void {
        $this->assertEquals('hello world', e('hello world'));
    }

    // ── asset() ──────────────────────────────────────────────────────────────

    public function testAssetPrependsAssetsPath(): void {
        $this->assertEquals('/assets/css/app.css', asset('css/app.css'));
        $this->assertEquals('/assets/js/app.js',   asset('/js/app.js'));
    }

    // ── json() ───────────────────────────────────────────────────────────────

    public function testJsonEncodesArray(): void {
        $result = json(['key' => 'value']);
        $this->assertJson($result);
        $this->assertEquals(['key' => 'value'], \json_decode($result, true));
    }

    public function testJsonEscapesHtmlChars(): void {
        $result = json(['html' => '<script>alert(1)</script>']);
        // JSON_HEX_TAG encodes < and > as \u003C \u003E
        $this->assertStringNotContainsString('<script>', $result);
    }

    // ── ANSI colour functions ─────────────────────────────────────────────────

    /** @return array<string, array{callable}> */
    public static function colourProvider(): array {
        return [
            'rouge'      => ['rouge'],
            'vert'       => ['vert'],
            'jaune'      => ['jaune'],
            'bleu'       => ['bleu'],
            'violet'     => ['violet'],
            'cyan'       => ['cyan'],
            'blanc'      => ['blanc'],
            'grisClair'  => ['grisClair'],
            'rougeVif'   => ['rougeVif'],
            'vertVif'    => ['vertVif'],
            'jauneVif'   => ['jauneVif'],
            'bleuVif'    => ['bleuVif'],
            'violetVif'  => ['violetVif'],
            'cyanVif'    => ['cyanVif'],
            'blancVif'   => ['blancVif'],
            'orange'     => ['orange'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('colourProvider')]
    public function testColourWrapsWithAnsiAndPreservesText(string $fn): void {
        $result = $fn('hello');
        $this->assertStringStartsWith("\033[", $result);
        $this->assertStringContainsString('hello', $result);
        $this->assertStringEndsWith("\033[0m", $result);
    }

    // ── view() / redirect() ───────────────────────────────────────────────────

    public function testViewReturnsResponse(): void {
        $response = view('module.index', ['foo' => 'bar']);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testRedirectReturnsResponse(): void {
        $response = redirect(['controller' => 'Module', 'action' => 'index']);
        $this->assertInstanceOf(Response::class, $response);
    }

    // ── since() ───────────────────────────────────────────────────────────────

    public function testSinceReturnsJustNowForCurrentTime(): void {
        $result = since(new \DateTime());
        $this->assertEquals('just now', $result);
    }

    public function testSinceReturnsRelativeTime(): void {
        $past = new \DateTime('-2 hours');
        $result = since($past);
        $this->assertStringContainsString('hour', $result);
    }

    public function testSinceFullMode(): void {
        $past = new \DateTime('-1 year -2 months');
        $result = since($past, true);
        $this->assertStringContainsString('year', $result);
        $this->assertStringContainsString('month', $result);
    }

    // ── noir() ────────────────────────────────────────────────────────────────

    public function testNoirWrapsWithAnsi(): void {
        $result = noir('test');
        $this->assertStringStartsWith("\033[", $result);
        $this->assertStringContainsString('test', $result);
    }

    // ── url() ─────────────────────────────────────────────────────────────────

    public function testUrlReturnsString(): void {
        $url = url(['controller' => 'Module', 'action' => 'index']);
        $this->assertIsString($url);
        $this->assertStringContainsString('module', $url);
    }

    // ── csrf_field() ─────────────────────────────────────────────────────────

    public function testCsrfFieldReturnsHiddenInput(): void {
        $html = csrf_field();
        $this->assertStringContainsString('_token', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    // ── old() ─────────────────────────────────────────────────────────────────

    public function testOldReturnsDefaultWhenEmpty(): void {
        $_SESSION = [];
        $this->assertEquals('', old('name'));
        $this->assertEquals('fallback', old('name', 'fallback'));
    }

    public function testOldReturnsSessionValue(): void {
        $_SESSION['old'] = ['name' => 'Simon'];
        $this->assertEquals('Simon', old('name'));
    }

    // ── error() ──────────────────────────────────────────────────────────────

    public function testErrorReturnsEmptyStringWhenNoErrors(): void {
        $_SESSION = [];
        $this->assertEquals('', error('name'));
    }

    public function testErrorReturnsSpanWhenErrorExists(): void {
        $_SESSION['errors'] = ['name' => 'Champ requis'];
        $result = error('name');
        $this->assertStringContainsString('Champ requis', $result);
        $this->assertStringContainsString('<span', $result);
    }
}
