<?php

namespace SkankyTest\TestCase\View;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;
use SkankyDev\View\HtmlView;
use TestApp\View\Part\GreetPart;

class HtmlViewRenderTest extends TestCase
{
    private array $bootstrapConf;

    protected function setUp(): void
    {
        (new \ReflectionProperty(Router::class,     '_instance'))->setValue(null, null);
        (new \ReflectionProperty(UrlBuilder::class, '_instance'))->setValue(null, null);
        Router::_findCurrentRoute('/test/index');
        // part() peut écrire dans Config (class.parts, curentNamespace) — on isole chaque test.
        $this->bootstrapConf = Config::$conf;
    }

    protected function tearDown(): void
    {
        Config::$conf = $this->bootstrapConf;
    }

    // ── makePath ──────────────────────────────────────────────────────────────

    public function testMakePathReturnsAbsolutePath(): void
    {
        $view = new HtmlView();
        $path = $view->makePath('_test.index');
        $this->assertStringEndsWith('_test' . DIRECTORY_SEPARATOR . 'index.php', $path);
        $this->assertFileExists($path);
    }

    public function testMakePathThrowsForUnknownView(): void
    {
        $view = new HtmlView();
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(601);
        $view->makePath('does.not.exist');
    }

    // ── render sans layout ────────────────────────────────────────────────────

    public function testRenderWithoutLayout(): void
    {
        $view = new HtmlView('_test.index', ['title' => 'Bonjour']);
        $view->setLayout(null);
        $output = $view->render();

        $this->assertStringContainsString('<h1>Bonjour</h1>', $output);
    }

    public function testRenderClosesBuffersWhenTemplateThrows(): void
    {
        $level = ob_get_level();
        $view  = new HtmlView('_test.throws');
        $view->setLayout(null);

        try {
            $view->render();
            $this->fail('The template exception should propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($level, ob_get_level());
        $stack = (new \ReflectionProperty(HtmlView::class, 'blockStack'))->getValue($view);
        $this->assertSame([], $stack);
    }

    public function testRenderEscapesVariables(): void
    {
        $view = new HtmlView('_test.index', ['title' => '<script>xss</script>']);
        $view->setLayout(null);
        $output = $view->render();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testRenderShowTemplate(): void
    {
        $view = new HtmlView('_test.show', ['item' => 'mon objet']);
        $view->setLayout(null);
        $output = $view->render();

        $this->assertStringContainsString('<p>mon objet</p>', $output);
    }

    // ── render avec layout ────────────────────────────────────────────────────

    public function testRenderWithLayout(): void
    {
        $view = new HtmlView('_test.index', ['title' => 'Avec layout']);
        $view->setLayout('_test_layout.default');
        $output = $view->render();

        $this->assertStringContainsString('<body>', $output);
        $this->assertStringContainsString('<h1>Avec layout</h1>', $output);
    }

    public function testRenderInjectsContentInLayout(): void
    {
        $view = new HtmlView('_test.index', ['title' => 'Test']);
        $view->setLayout('_test_layout.default');
        $view->setTitle('Page titre');
        $output = $view->render();

        $this->assertStringContainsString('<title>Page titre</title>', $output);
        $this->assertStringContainsString('<h1>Test</h1>', $output);
    }

    public function testRenderLayoutIncludesAddedCss(): void
    {
        $view = new HtmlView('_test.index', ['title' => 'CSS']);
        $view->setLayout('_test_layout.default');
        $view->addCss('/assets/app.css');
        $output = $view->render();

        $this->assertStringContainsString('/assets/app.css', $output);
    }

    public function testRenderLayoutIncludesScript(): void
    {
        $view = new HtmlView('_test.index', ['title' => 'JS']);
        $view->setLayout('_test_layout.default');
        $view->startScript();
        echo 'var x = 1;';
        $view->stopScript();
        $output = $view->render();

        $this->assertStringContainsString('var x = 1;', $output);
    }

    // ── viewPath / layoutPath ─────────────────────────────────────────────────

    public function testViewPathReturnsPath(): void
    {
        $view = new HtmlView('_test.index');
        $this->assertStringEndsWith('index.php', $view->viewPath());
    }

    public function testLayoutPathReturnsPath(): void
    {
        $view = new HtmlView('_test.index');
        $view->setLayout('_test_layout.default');
        $this->assertStringEndsWith('default.php', $view->layoutPath());
    }

    // ── part ──────────────────────────────────────────────────────────────────

    public function testPartRendersSubTemplate(): void
    {
        $view   = new HtmlView('_test.index', ['title' => 'unused']);
        $output = $view->part('_test.part', ['msg' => 'bonjour']);

        $this->assertStringContainsString('<span>bonjour</span>', $output);
    }

    public function testPartEscapesVariables(): void
    {
        $view   = new HtmlView('_test.index', ['title' => 'unused']);
        $output = $view->part('_test.part', ['msg' => '<b>xss</b>']);

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringContainsString('&lt;b&gt;', $output);
    }

    public function testPartResolvesClassFromCurrentNamespaceConvention(): void
    {
        Config::setCurrentNamespace('TestApp');
        $view   = new HtmlView();
        $output = $view->part('part.greet', ['name' => 'Simon']);

        $this->assertStringContainsString('Salut Simon', $output);
    }

    public function testPartClassOverrideFromConfigTakesPriorityOverConvention(): void
    {
        // Sans override, la convention chercherait TestApp\View\Part\MysteryPart, qui n'existe pas.
        Config::setCurrentNamespace('TestApp');
        Config::set('class.parts', ['part.mystery' => GreetPart::class]);

        $view   = new HtmlView();
        $output = $view->part('part.mystery', ['name' => 'Simon']);

        $this->assertStringContainsString('Salut Simon', $output);
    }
}
