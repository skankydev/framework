<?php

namespace SkankyTest\TestCase\Routing;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Http\Routing\Route\CurrentRoute;
use SkankyDev\Http\Routing\Route\Route;

class CurrentRouteTest extends TestCase
{
    private array $bootstrapConf;

    protected function setUp(): void {
        // CurrentRoute écrit dans Config (curentNamespace) — on isole chaque test.
        $this->bootstrapConf = Config::$conf;
    }

    protected function tearDown(): void {
        Config::$conf = $this->bootstrapConf;
    }

    // ── Convention-based parsing (initFromUri) ────────────────────────────────

    public function testSimpleControllerAction(): void {
        $current = new CurrentRoute('/post/add');
        $this->assertEquals('App\Controller\PostController', $current->getController());
        $this->assertEquals('add', $current->getAction());
        $this->assertEquals('App', $current->getNamespace());
    }

    public function testDefaultActionWhenMissing(): void {
        $current = new CurrentRoute('/module');
        $this->assertEquals('App\Controller\ModuleController', $current->getController());
        $this->assertEquals('index', $current->getAction());
    }

    public function testParamsAreExtracted(): void {
        $current = new CurrentRoute('/module/show/abc123');
        $this->assertEquals('show', $current->getAction());
        $this->assertEquals(['abc123'], $current->getParams());
    }

    public function testMultipleParams(): void {
        $current = new CurrentRoute('/module/show/abc/def');
        $this->assertEquals(['abc', 'def'], $current->getParams());
    }

    public function testNoParamsReturnsEmptyArray(): void {
        $current = new CurrentRoute('/module/index');
        $this->assertEquals([], $current->getParams());
    }

    public function testDashCaseControllerConvertedToPascalCase(): void {
        $current = new CurrentRoute('/my-module/index');
        $this->assertEquals('App\Controller\MyModuleController', $current->getController());
    }

    // ── Declared route parsing (initFromRoute) ────────────────────────────────

    public function testInitFromRouteWithNamedSegment(): void {
        $route   = new Route('/article/:slug', ['controller' => 'App\Controller\PostController', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]+',
        ]);
        $current = new CurrentRoute('/article/my-post', $route);

        $this->assertEquals('App\Controller\PostController', $current->getController());
        $this->assertEquals('view', $current->getAction());
        $this->assertEquals(['slug' => 'my-post'], $current->getParams());
    }

    public function testInitFromRouteWithNoSegments(): void {
        $route   = new Route('/', ['controller' => 'App\Controller\HomeController', 'action' => 'index']);
        $current = new CurrentRoute('/', $route);

        $this->assertEquals('App\Controller\HomeController', $current->getController());
        $this->assertEquals('index', $current->getAction());
        $this->assertEquals([], $current->getParams());
    }

    // ── Link array ────────────────────────────────────────────────────────────

    public function testGetLinkReturnsFullArray(): void {
        $current = new CurrentRoute('/post/show/42');
        $link    = $current->getLink();
        $this->assertArrayHasKey('namespace',  $link);
        $this->assertArrayHasKey('controller', $link);
        $this->assertArrayHasKey('action',     $link);
    }

    public function testMiddlewaresFromRoute(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $route->setMiddlewares(['Auth', 'Session']);
        $current = new CurrentRoute('/', $route);
        $this->assertEquals(['Auth', 'Session'], $current->getMiddlewares());
    }

    // ── Config::curentNamespace synchronisation ────────────────────────────────

    public function testInitFromUriSetsCurentNamespaceInConfig(): void {
        new CurrentRoute('/post/add');
        $this->assertEquals('App', Config::getCurrentNamespace());
    }

    public function testInitFromRouteSetsCurentNamespaceInConfig(): void {
        $route = new Route('/', ['controller' => 'App\Controller\HomeController', 'action' => 'index', 'namespace' => 'Admin']);
        new CurrentRoute('/', $route);
        $this->assertEquals('Admin', Config::getCurrentNamespace());
    }
}
