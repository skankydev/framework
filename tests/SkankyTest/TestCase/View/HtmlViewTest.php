<?php

namespace SkankyTest\TestCase\View;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;
use SkankyDev\View\HtmlView;

class HtmlViewTest extends TestCase
{
    private array $bootstrapConf;

    protected function setUp(): void
    {
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
        $ref = new \ReflectionProperty(UrlBuilder::class, '_instance');
        $ref->setValue(null, null);
        Router::_findCurrentRoute('/module/index');
        $this->bootstrapConf = Config::$conf;
    }

    protected function tearDown(): void
    {
        Config::$conf = $this->bootstrapConf;
    }

    // ── layout ────────────────────────────────────────────────────────────────

    public function testDefaultLayoutIsSet(): void
    {
        $view = new HtmlView('module.index');
        $this->assertEquals('layout.default', $view->layout);
    }

    public function testDefaultLayoutComesFromConfig(): void
    {
        Config::set('view.layout', 'layout.custom');
        $view = new HtmlView('module.index');
        $this->assertEquals('layout.custom', $view->layout);
    }

    public function testSetLayoutChangesLayout(): void
    {
        $view = new HtmlView('module.index');
        $view->setLayout('layout.admin');
        $this->assertEquals('layout.admin', $view->layout);
    }

    public function testSetLayoutNullDisablesLayout(): void
    {
        $view = new HtmlView('module.index');
        $view->setLayout(null);
        $this->assertNull($view->layout);
    }

    public function testSetLayoutReturnsSelf(): void
    {
        $view = new HtmlView();
        $this->assertSame($view, $view->setLayout(null));
    }

    // ── title ─────────────────────────────────────────────────────────────────

    public function testSetAndGetTitle(): void
    {
        $view = new HtmlView();
        $view->setTitle('Ma page');
        $this->assertEquals('Ma page', $view->getTitle());
    }

    // ── css / js ──────────────────────────────────────────────────────────────

    public function testAddCssAppendsLinkTag(): void
    {
        $view = new HtmlView();
        $view->addCss('/assets/css/app.css');
        $this->assertStringContainsString('<link', $view->css);
        $this->assertStringContainsString('/assets/css/app.css', $view->css);
    }

    public function testAddCssAppendsMultiple(): void
    {
        $view = new HtmlView();
        $view->addCss('/a.css');
        $view->addCss('/b.css');
        $this->assertStringContainsString('/a.css', $view->css);
        $this->assertStringContainsString('/b.css', $view->css);
    }

    public function testAddJsAppendsScriptTag(): void
    {
        $view = new HtmlView();
        $view->addJs('/assets/js/app.js');
        $this->assertStringContainsString('<script', $view->js);
        $this->assertStringContainsString('/assets/js/app.js', $view->js);
    }

    // ── meta / keywords ───────────────────────────────────────────────────────

    public function testAddMetaStoresEntry(): void
    {
        $view = new HtmlView();
        $view->addMeta('description', 'Mon site');
        $this->assertEquals('Mon site', $view->meta['description']);
    }

    public function testAddKeywordsAppends(): void
    {
        $view = new HtmlView();
        $view->addKeyWords('php,framework');
        $this->assertStringContainsString('php,framework', $view->keywords);
    }

    // ── getHeader ─────────────────────────────────────────────────────────────

    public function testGetHeaderIncludesMeta(): void
    {
        $view = new HtmlView();
        $view->addMeta('author', 'Simon');
        $view->addCss('/app.css');
        $view->addJs('/app.js');

        $header = $view->getHeader();
        $this->assertStringContainsString('author', $header);
        $this->assertStringContainsString('/app.css', $header);
        $this->assertStringContainsString('/app.js', $header);
    }

    // ── script buffering ──────────────────────────────────────────────────────

    public function testScriptBuffering(): void
    {
        $view = new HtmlView();
        $view->startScript();
        echo 'console.log("hello");';
        $view->stopScript();

        $this->assertStringContainsString('console.log', $view->getScript());
    }

    public function testStopScriptAppendsToExistingScript(): void
    {
        $view = new HtmlView();
        $view->startScript();
        echo 'var a = 1;';
        $view->stopScript();
        $view->startScript();
        echo 'var b = 2;';
        $view->stopScript();

        $script = $view->getScript();
        $this->assertStringContainsString('var a = 1;', $script);
        $this->assertStringContainsString('var b = 2;', $script);
    }

    // ── blocks ────────────────────────────────────────────────────────────────

    public function testBlockBuffering(): void
    {
        $view = new HtmlView();
        $view->startBlock('sidebar');
        echo 'contenu sidebar';
        $view->stopBlock();

        $this->assertStringContainsString('contenu sidebar', $view->getBlock('sidebar'));
    }

    public function testGetBlockReturnsEmptyStringWhenNeverStarted(): void
    {
        $view = new HtmlView();
        $this->assertSame('', $view->getBlock('nope'));
    }

    public function testStopBlockAppendsToExistingBlock(): void
    {
        $view = new HtmlView();
        $view->startBlock('sidebar');
        echo 'premier';
        $view->stopBlock();
        $view->startBlock('sidebar');
        echo 'second';
        $view->stopBlock();

        $content = $view->getBlock('sidebar');
        $this->assertStringContainsString('premier', $content);
        $this->assertStringContainsString('second', $content);
    }

    public function testBlocksAreIndependentByName(): void
    {
        $view = new HtmlView();
        $view->startBlock('a');
        echo 'contenu a';
        $view->stopBlock();
        $view->startBlock('b');
        echo 'contenu b';
        $view->stopBlock();

        $this->assertSame('contenu a', $view->getBlock('a'));
        $this->assertSame('contenu b', $view->getBlock('b'));
    }

    public function testNestedBlocksResumeTheOuterOneOnClose(): void
    {
        $view = new HtmlView();
        $view->startBlock('outer');
        echo 'avant-';
        $view->startBlock('inner');
        echo 'dedans';
        $view->stopBlock();
        echo '-apres';
        $view->stopBlock();

        $this->assertSame('dedans', $view->getBlock('inner'));
        $this->assertSame('avant--apres', $view->getBlock('outer'));
    }

    public function testStartScriptIsAThinWrapperOverBlockScript(): void
    {
        $view = new HtmlView();
        $view->startScript();
        echo 'console.log(1);';
        $view->stopScript();

        $this->assertSame($view->getScript(), $view->getBlock('script'));
    }

    // ── fetch ─────────────────────────────────────────────────────────────────

    public function testFetchEchoesProperty(): void
    {
        $view        = new HtmlView();
        $view->title = 'Test title';

        ob_start();
        $view->fetch('title');
        $output = ob_get_clean();

        $this->assertEquals('Test title', $output);
    }

    // ── breadcrumbs ───────────────────────────────────────────────────────────

    public function testAddCrumbWithStringUrl(): void
    {
        $view = new HtmlView();
        $view->addCrumb('Accueil', '/');

        $this->assertCount(1, $view->breadcrumbInfo);
        $this->assertEquals('Accueil', $view->breadcrumbInfo[0]['label']);
        $this->assertEquals('/',       $view->breadcrumbInfo[0]['url']);
    }

    public function testAddCrumbWithIcon(): void
    {
        $view = new HtmlView();
        $view->addCrumb('Home', '/', 'icon-home');
        $this->assertEquals('icon-home', $view->breadcrumbInfo[0]['icon']);
    }

    public function testAddCrumbWithArrayUrl(): void
    {
        $view = new HtmlView();
        $view->addCrumb('Modules', ['controller' => 'Module', 'action' => 'index']);
        $this->assertEquals('/module', $view->breadcrumbInfo[0]['url']);
    }

    public function testAddMultipleCrumbs(): void
    {
        $view = new HtmlView();
        $view->addCrumb('Accueil', '/');
        $view->addCrumb('Modules', '/module');
        $this->assertCount(2, $view->breadcrumbInfo);
    }

    // ── HtmlHelper ────────────────────────────────────────────────────────────

    public function testCreateAttrBuildsAttributeString(): void
    {
        $view   = new HtmlView();
        $result = $view->createAttr(['class' => 'btn', 'id' => 'submit']);
        $this->assertStringContainsString('class="btn"', $result);
        $this->assertStringContainsString('id="submit"', $result);
    }

    public function testCreateAttrReturnsEmptyStringForNoAttributes(): void
    {
        $view = new HtmlView();
        $this->assertEquals('', $view->createAttr([]));
    }

    public function testSurroundWrapsContentInTag(): void
    {
        $view   = new HtmlView();
        $result = $view->surround('texte', 'span', ['class' => 'highlight']);
        $this->assertStringStartsWith('<span', $result);
        $this->assertStringContainsString('texte', $result);
        $this->assertStringEndsWith('</span>', $result);
        $this->assertStringContainsString('class="highlight"', $result);
    }

    public function testLinkGeneratesAnchorTag(): void
    {
        $view   = new HtmlView();
        $result = $view->link('Modules', ['controller' => 'Module', 'action' => 'index']);
        $this->assertStringContainsString('<a', $result);
        $this->assertStringContainsString('href=', $result);
        $this->assertStringContainsString('Modules', $result);
        $this->assertStringContainsString('</a>', $result);
    }

    public function testUrlReturnsString(): void
    {
        $view = new HtmlView();
        $url  = $view->url(['controller' => 'Module', 'action' => 'index']);
        $this->assertIsString($url);
        $this->assertStringContainsString('/module', $url);
    }
}
