<?php

namespace SkankyTest\TestCase\Routing;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\Routing\Route\Route;
use SkankyDev\Http\Routing\Route\CurrentRoute;

class RouterTest extends TestCase
{
    protected function setUp(): void {
        // Reset Singleton so each test starts with an empty route collection
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
    }

    public function testAddRouteInRouter(): void {
        Router::_add('/', ['controller' => 'Home', 'action' => 'index']);
        $this->assertCount(1, Router::_getRoutesCollection());
    }

    public function testAddedRouteIsInstanceOfRoute(): void {
        $route = Router::_add('/', ['controller' => 'Home', 'action' => 'index']);
        $this->assertInstanceOf(Route::class, $route);
    }

    public function testMatchRouteInCollection(): void {
        Router::_add('/', ['controller' => 'Home', 'action' => 'index']);
        Router::_add('/article/:slug', ['controller' => 'Post', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]*',
        ]);

        $route = Router::_matchRouteUri('/');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/', $route->getShema());

        $route = Router::_matchRouteUri('/article/youpi-test');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/article/:slug', $route->getShema());

        // No declared route → returns null (convention fallback handled elsewhere)
        $this->assertNull(Router::_matchRouteUri('/post/add'));
    }

    public function testFindCurrentRouteFromDeclaredRoute(): void {
        Router::_add('/', ['controller' => 'App\Controller\HomeController', 'action' => 'index']);

        $current = Router::_findCurrentRoute('/');
        $this->assertInstanceOf(CurrentRoute::class, $current);
        $this->assertEquals('App\Controller\HomeController', $current->getController());
    }

    public function testFindByNameReturnsDeclaredRoute(): void {
        Router::_add('/', ['controller' => 'App\Controller\HomeController', 'action' => 'index'])->setName('home');
        Router::_add('/login', ['controller' => 'App\Controller\Auth\LoginController', 'action' => 'login'])->setName('login');

        $route = Router::_findByName('login');
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/login', $route->getShema());
    }

    public function testFindByNameReturnsNullWhenNotFound(): void {
        Router::_add('/', ['controller' => 'App\Controller\HomeController', 'action' => 'index'])->setName('home');
        $this->assertNull(Router::_findByName('unknown'));
    }

    public function testFindCurrentRouteFallsBackToConvention(): void {
        // No declared routes → initFromUri() handles convention parsing
        $current = Router::_findCurrentRoute('/post/add');
        $this->assertInstanceOf(CurrentRoute::class, $current);
        $this->assertEquals('App\Controller\PostController', $current->getController());
        $this->assertEquals('add', $current->getAction());
    }

    public function testGetCurrentRouteReturnsSameInstance(): void {
        Router::_add('/', ['controller' => 'Home', 'action' => 'index']);
        $found = Router::_findCurrentRoute('/');
        $current = Router::_getCurrentRoute();
        $this->assertEquals($found->getLink(), $current->getLink());
    }

    public function testNoRoutesMatchReturnsNull(): void {
        // Empty collection
        $this->assertNull(Router::_matchRouteUri('/anything'));
    }
}
