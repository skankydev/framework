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
}
