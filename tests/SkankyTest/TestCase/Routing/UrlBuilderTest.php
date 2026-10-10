<?php

namespace SkankyTest\TestCase\Routing;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Exception\RouteNotFoundException;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\Routing\Route\Route;
use SkankyDev\Http\UrlBuilder;

class UrlBuilderTest extends TestCase
{
    private array $bootstrapConf;

    protected function setUp(): void {
        // Reset both Singletons — UrlBuilder depends on Router::_getCurrentRoute()
        $refRouter = new \ReflectionProperty(Router::class, '_instance');
        $refRouter->setValue(null, null);

        $refBuilder = new \ReflectionProperty(UrlBuilder::class, '_instance');
        $refBuilder->setValue(null, null);

        // Config::curentNamespace est écrit par CurrentRoute — on isole chaque test
        $this->bootstrapConf = Config::$conf;

        // Establish a current route so completLink() has something to inherit from
        Router::_findCurrentRoute('/post/index');
    }

    protected function tearDown(): void {
        Config::$conf = $this->bootstrapConf;
    }

    public function testCompletLinkInheritsCurrentRoute(): void {
        $link = UrlBuilder::_completLink(['action' => 'add']);
        $this->assertEquals('App',  $link['namespace']);
        $this->assertEquals('Post', $link['controller']);
        $this->assertEquals('add',  $link['action']);
    }

    public function testMatchWithDeclaredRoute(): void {
        Router::_add('/article/:slug', ['controller' => 'Post', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]*',
        ]);

        $route = UrlBuilder::_matcheWithRoute([
            'namespace'  => 'App',
            'controller' => 'Post',
            'action'     => 'view',
        ]);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals('/article/:slug', $route->getShema());
    }

    public function testNoMatchReturnsNull(): void {
        $result = UrlBuilder::_matcheWithRoute([
            'namespace'  => 'App',
            'controller' => 'Unknown',
            'action'     => 'index',
        ]);
        $this->assertNull($result);
    }

    public function testBuildFromMatchingRoute(): void {
        Router::_add('/article/:slug', ['controller' => 'Post', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]*',
        ]);
        $url = UrlBuilder::_build(['controller' => 'Post', 'action' => 'view', 'params' => ['youpi-test']]);
        $this->assertEquals('/article/youpi-test', $url);
    }

    public function testBuildFromDefaultConvention(): void {
        $url = UrlBuilder::_build(['controller' => 'Message', 'action' => 'view', 'params' => ['youpi-test']]);
        $this->assertEquals('/message/view/youpi-test', $url);
    }

    public function testBuildFromDefaultConventionOmitsDefaultNamespace(): void {
        $url = UrlBuilder::_createUrlFromDefault(['namespace' => 'App', 'controller' => 'Message', 'action' => 'view']);
        $this->assertEquals('/message/view', $url);
    }

    public function testBuildFromDefaultConventionOmitsDefaultNamespaceFromOtherModule(): void {
        // depuis une page Admin, un lien vers App n'a pas besoin du préfixe
        Config::setCurrentNamespace('Admin');
        $url = UrlBuilder::_createUrlFromDefault(['namespace' => 'App', 'controller' => 'Message', 'action' => 'view']);
        $this->assertEquals('/message/view', $url);
    }

    public function testBuildFromDefaultConventionKeepsCurrentNonDefaultNamespace(): void {
        // régression : depuis une page Admin, un lien vers Admin doit garder /admin,
        // sinon l'URI sans préfixe est résolue dans le namespace par défaut (App)
        Config::setCurrentNamespace('Admin');
        $url = UrlBuilder::_createUrlFromDefault(['namespace' => 'Admin', 'controller' => 'Post', 'action' => 'create']);
        $this->assertEquals('/admin/post/create', $url);
    }

    public function testBuildOmitsDefaultAction(): void {
        $url = UrlBuilder::_build(['controller' => 'Module', 'action' => 'index']);
        $this->assertEquals('/module', $url);
    }

    public function testBuildFromNamedRoute(): void {
        Router::_add('/article/:slug', ['controller' => 'Post', 'action' => 'view'], [
            'slug' => '[a-zA-Z0-9\-]*',
        ])->setName('article-view');

        $url = UrlBuilder::_build(['name' => 'article-view', 'params' => ['youpi-test']]);
        $this->assertEquals('/article/youpi-test', $url);
    }

    public function testBuildFromNamedRouteWithoutParams(): void {
        Router::_add('/login', ['controller' => 'Auth\Login', 'action' => 'login'])->setName('login');

        $url = UrlBuilder::_build(['name' => 'login']);
        $this->assertEquals('/login', $url);
    }

    public function testCompletLinkOnDeclaredRouteInheritsShortController(): void {
        // régression : sur une route déclarée en FQCN, un lien partiel héritait du FQCN
        Router::_add('/admin', ['controller' => 'Admin\Controller\DashboardController', 'action' => 'index']);
        Router::_findCurrentRoute('/admin');

        $link = UrlBuilder::_completLink(['action' => 'stats']);
        $this->assertEquals('Dashboard', $link['controller']);
        $this->assertEquals('Admin', $link['namespace']);
        $this->assertEquals('/admin/dashboard/stats', UrlBuilder::_build(['action' => 'stats']));
    }

    public function testConventionLinkMatchesRouteDeclaredWithFqcn(): void {
        Router::_add('/admin', ['controller' => 'Admin\Controller\DashboardController', 'action' => 'index']);

        $url = UrlBuilder::_build(['namespace' => 'Admin', 'controller' => 'Dashboard', 'action' => 'index']);
        $this->assertEquals('/admin', $url);
    }

    public function testBuildWithUnknownNameThrows(): void {
        $this->expectException(RouteNotFoundException::class);
        UrlBuilder::_build(['name' => 'unknown']);
    }

    public function testAddGetAppendsQueryString(): void {
        $url = UrlBuilder::_addGet('/article/youpi-test', ['page' => 1, 'order' => 'field']);
        $this->assertEquals('/article/youpi-test?page=1&order=field', $url);
    }

    public function testBuildCurrentReturnsUrlOfCurrentRoute(): void {
        // Current route is /post/index, default action = index → /post
        $url = UrlBuilder::_buildCurrent();
        $this->assertEquals('/post', $url);
    }

    public function testBuildCurrentAbsoluteIncludesSchemeAndHost(): void {
        // Reset Request singleton so it picks up $_SERVER
        $refRequest = new \ReflectionProperty(\SkankyDev\Http\Request::class, '_instance');
        $refRequest->setValue(null, null);

        $_SERVER['REQUEST_SCHEME'] = 'http';
        $_SERVER['HTTP_HOST']      = 'skankyblog.local';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/post/index';
        $_SERVER['REMOTE_ADDR']    = '127.0.0.1';

        $url = UrlBuilder::_buildCurrent(true);
        $this->assertStringStartsWith('http://skankyblog.local', $url);
    }
}
