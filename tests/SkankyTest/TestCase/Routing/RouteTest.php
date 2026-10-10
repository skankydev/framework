<?php

namespace SkankyTest\TestCase\Routing;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Routing\Route\Route;

class RouteTest extends TestCase
{
    public function testGetSchema(): void {
        $route = new Route('/article/:slug', ['controller' => 'Post', 'action' => 'view']);
        $this->assertEquals('/article/:slug', $route->getShema());
    }

    public function testLinkDefaultsAreFilledIn(): void {
        $route = new Route('/', ['controller' => 'Home']);
        $link  = $route->getLink();
        // action and namespace should be filled from Config defaults
        $this->assertEquals('Home',  $link['controller']);
        $this->assertEquals('index', $link['action']);
        $this->assertEquals('App',   $link['namespace']);
    }

    public function testExplicitLinkValuesAreKept(): void {
        $route = new Route('/post/view', ['controller' => 'Post', 'action' => 'view', 'namespace' => 'App']);
        $link  = $route->getLink();
        $this->assertEquals('Post', $link['controller']);
        $this->assertEquals('view', $link['action']);
    }

    public function testGetRules(): void {
        $rules = ['slug' => '[a-z0-9\-]+'];
        $route = new Route('/article/:slug', ['controller' => 'Post', 'action' => 'view'], $rules);
        $this->assertEquals($rules, $route->getRules());
    }

    public function testSimpleRouteRegexMatchesUri(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $regex = $route->getMatcheRules();
        $this->assertEquals(1, preg_match($regex, '/'));
        $this->assertEquals(0, preg_match($regex, '/other'));
    }

    public function testParameterisedRouteRegexMatchesUri(): void {
        $route = new Route('/article/:slug', ['controller' => 'Post', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]+',
        ]);
        $regex = $route->getMatcheRules();
        $this->assertEquals(1, preg_match($regex, '/article/my-post-title'));
        $this->assertEquals(0, preg_match($regex, '/article/'));
        $this->assertEquals(0, preg_match($regex, '/other/my-post-title'));
    }

    public function testRegexIsCompiledOnFirstCallOnly(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        // Two calls must return the same regex
        $this->assertEquals($route->getMatcheRules(), $route->getMatcheRules());
    }

    public function testMiddlewares(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $this->assertEquals([], $route->getMiddlewares());

        $route->setMiddlewares(['Auth']);
        $this->assertEquals(['Auth'], $route->getMiddlewares());
    }

    public function testNameIsNullByDefault(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $this->assertNull($route->getName());
    }

    public function testSetNameReturnsSelfAndIsRetrievable(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $result = $route->setName('home');
        $this->assertSame($route, $result);
        $this->assertEquals('home', $route->getName());
    }

    public function testSetMiddlewaresAndSetNameAreChainable(): void {
        $route = new Route('/', ['controller' => 'Home', 'action' => 'index']);
        $result = $route->setMiddlewares(['Auth'])->setName('home');
        $this->assertSame($route, $result);
        $this->assertEquals('home', $route->getName());
        $this->assertEquals(['Auth'], $route->getMiddlewares());
    }

    // ── Controller normalization (FQCN → short name) ──────────────────────────

    public function testFqcnControllerIsShortenedAndNamespaceDerived(): void {
        $route = new Route('/admin', ['controller' => 'Admin\Controller\DashboardController']);
        $link  = $route->getLink();
        $this->assertEquals('Dashboard', $link['controller']);
        $this->assertEquals('Admin', $link['namespace']);
        $this->assertEquals('Admin\Controller\DashboardController', $route->getControllerClass());
    }

    public function testFqcnSubFolderControllerKeepsItsFolder(): void {
        $route = new Route('/login', ['controller' => 'App\Controller\Auth\LoginController', 'action' => 'login']);
        $this->assertEquals('Auth\Login', $route->getLink()['controller']);
        $this->assertEquals('App\Controller\Auth\LoginController', $route->getControllerClass());
    }

    public function testExplicitNamespaceWinsOverFqcn(): void {
        $route = new Route('/', ['controller' => 'App\Controller\HomeController', 'namespace' => 'Admin']);
        $this->assertEquals('Admin', $route->getLink()['namespace']);
        $this->assertEquals('App\Controller\HomeController', $route->getControllerClass());
    }

    public function testShortControllerResolvesClassFromNamespace(): void {
        $route = new Route('/', ['controller' => 'Post', 'namespace' => 'Admin']);
        $this->assertEquals('Post', $route->getLink()['controller']);
        $this->assertEquals('Admin\Controller\PostController', $route->getControllerClass());
    }
}
