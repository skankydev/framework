<?php

namespace SkankyTest\TestCase\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Exception\MiddlewareNotFoundException;
use SkankyDev\Http\Middleware\Attribute\Middleware;
use SkankyDev\Http\Middleware\MiddlewareInterface;
use SkankyDev\Http\Middleware\MiddlewareManager;
use SkankyDev\Http\Routing\Route\CurrentRoute;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\Request;
use SkankyDev\Config\Config;

// Simple pass-through middleware with no constructor dependencies
class PassMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed {
        $_SERVER['_mw_ran'] = true;
        return $next($request);
    }
}

// Fixture : un controller dont les middlewares sont déclarés par attributs.
// #[Middleware] sur la classe → tout le controller ; sur la méthode → cette action.
#[Middleware(AlphaMiddleware::class)]
class AnnotatedController {

    #[Middleware(BetaMiddleware::class)]
    public function edit(): string {
        return 'edit-ok';
    }

    public function index(): string {
        return 'index-ok';
    }
}

// Fixtures dédiées au test d'exécution : middlewares avec trace PARTAGÉE, pour
// vérifier l'ordre oignon entrelacé indépendamment des middlewares POC de l'app.
class OnionTrace {
    public static array $steps = [];
}

class AlphaMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed {
        OnionTrace::$steps[] = 'Alpha:before';
        $response = $next($request);
        OnionTrace::$steps[] = 'Alpha:after';
        return $response;
    }
}

class BetaMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next): mixed {
        OnionTrace::$steps[] = 'Beta:before';
        $response = $next($request);
        OnionTrace::$steps[] = 'Beta:after';
        return $response;
    }
}

#[Middleware(AlphaMiddleware::class)]
class OnionController {
    #[Middleware(BetaMiddleware::class)]
    public function edit(): string {
        return 'edit-ok';
    }
}

// Fixture : middleware qui reçoit un argument de constructeur (param sans défaut,
// pour que la valeur positionnelle passe bien via MasterFactory).
class ArgMiddleware implements MiddlewareInterface {
    public function __construct(private string $label) {}
    public function handle(Request $request, callable $next): mixed {
        OnionTrace::$steps[] = "Arg:{$this->label}";
        return $next($request);
    }
}

#[Middleware(ArgMiddleware::class, 'edit-posts')]
class ArgController {
    public function go(): string {
        return 'go-ok';
    }
}

class MiddlewareManagerTest extends TestCase
{
    protected function setUp(): void {
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);

        $_GET = $_POST = $_COOKIE = $_FILES = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/',
            'REMOTE_ADDR'    => '127.0.0.1',
            '_mw_log'        => '',
        ];

        // No default middlewares for these tests
        Config::set('middlewares',       []);
        Config::set('class.middlewares', [
            'Pass' => PassMiddleware::class,
        ]);
    }

    private function makeRequest(): Request {
        return new Request();
    }

    private function makeRoute(array $middlewares = []): CurrentRoute {
        Router::_findCurrentRoute('/test/index');
        $current = Router::_getCurrentRoute();
        $current->setMiddlewares($middlewares);
        return $current;
    }

    public function testPipelineCallsDestinationWithNoMiddlewares(): void {
        $manager  = new MiddlewareManager();
        $request  = $this->makeRequest();
        $route    = $this->makeRoute([]);
        $called   = false;

        $manager->run($request, $route, function($req) use (&$called) {
            $called = true;
            return 'response';
        });

        $this->assertTrue($called);
    }

    public function testMiddlewareIsExecutedBeforeDestination(): void {
        Config::set('class.middlewares', ['Pass' => PassMiddleware::class]);
        $_SERVER['_mw_ran'] = false;

        $manager = new MiddlewareManager();
        $request = $this->makeRequest();
        $route   = $this->makeRoute(['Pass']);

        $manager->run($request, $route, fn($req) => 'ok');

        $this->assertTrue($_SERVER['_mw_ran']);
    }

    public function testPipelineReturnsDestinationReturnValue(): void {
        $manager = new MiddlewareManager();
        $request = $this->makeRequest();
        $route   = $this->makeRoute([]);

        $result = $manager->run($request, $route, fn($req) => 'my-response');
        $this->assertEquals('my-response', $result);
    }

    public function testRouteMiddlewaresMergedWithDefaults(): void {
        Config::set('middlewares', ['Pass']);
        Config::set('class.middlewares', ['Pass' => PassMiddleware::class]);

        $manager = new MiddlewareManager();
        $request = $this->makeRequest();
        $route   = $this->makeRoute([]); // no extra middlewares

        $reached = false;
        $manager->run($request, $route, function($req) use (&$reached) {
            $reached = true;
            return 'ok';
        });
        $this->assertTrue($reached);
    }

    // ── Middlewares déclarés par attributs sur le controller/action ─────────────

    public function testAttributeCollectsClassThenMethodMiddlewares(): void {
        $manager = new MiddlewareManager();

        // Classe d'abord (garde tout le controller), puis l'action — sous forme de specs
        $this->assertSame(
            [
                ['class' => AlphaMiddleware::class, 'args' => []],
                ['class' => BetaMiddleware::class,  'args' => []],
            ],
            $manager->attributeMiddlewares(AnnotatedController::class, 'edit')
        );
    }

    public function testAttributeCollectsOnlyClassWhenActionHasNone(): void {
        $manager = new MiddlewareManager();

        $this->assertSame(
            [['class' => AlphaMiddleware::class, 'args' => []]],
            $manager->attributeMiddlewares(AnnotatedController::class, 'index')
        );
    }

    public function testAttributeCarriesConstructorArgs(): void {
        $manager = new MiddlewareManager();

        // L'argument déclaré dans l'attribut est transporté dans la spec
        $this->assertSame(
            [['class' => ArgMiddleware::class, 'args' => ['edit-posts']]],
            $manager->attributeMiddlewares(ArgController::class, 'go')
        );
    }

    public function testAttributeArgReachesMiddlewareConstructor(): void {
        OnionTrace::$steps = [];

        $route = new class('/arg/go') extends CurrentRoute {
            public function getController(): string { return ArgController::class; }
            public function getAction(): string     { return 'go'; }
            public function getMiddlewares(): array  { return []; }
        };

        $manager = new MiddlewareManager();
        $manager->run($this->makeRequest(), $route, fn ($req) => 'ok');

        // ArgMiddleware a bien reçu 'edit-posts' dans son constructeur via MasterFactory
        $this->assertSame(['Arg:edit-posts'], OnionTrace::$steps);
    }

    public function testAttributeReturnsEmptyForUnknownController(): void {
        $manager = new MiddlewareManager();

        $this->assertSame([], $manager->attributeMiddlewares('App\\Controller\\Nope', 'index'));
    }

    // ── Middlewares avec arguments déclarés sur une route (setMiddlewares) ──────

    public function testRouteMiddlewareWithArgsReachesConstructor(): void {
        OnionTrace::$steps = [];
        Config::set('class.middlewares', ['Arg' => ArgMiddleware::class]);

        $manager = new MiddlewareManager();
        $route   = $this->makeRoute(['Arg' => ['edit-posts']]);

        $manager->run($this->makeRequest(), $route, fn($req) => 'ok');

        $this->assertSame(['Arg:edit-posts'], OnionTrace::$steps);
    }

    public function testRouteMiddlewareWithoutArgsStillWorks(): void {
        $_SERVER['_mw_ran'] = false;
        Config::set('class.middlewares', ['Pass' => PassMiddleware::class]);

        $manager = new MiddlewareManager();
        $route   = $this->makeRoute(['Pass']); // clé entière => pas d'args

        $manager->run($this->makeRequest(), $route, fn($req) => 'ok');

        $this->assertTrue($_SERVER['_mw_ran']);
    }

    public function testRunThrowsMiddlewareNotFoundForUnknownMiddleware(): void {
        $manager = new MiddlewareManager();
        $route   = $this->makeRoute(['App\\Middlewares\\GhostMiddleware']);

        $this->expectException(MiddlewareNotFoundException::class);
        $manager->run($this->makeRequest(), $route, fn ($req) => 'never-reached');
    }

    public function testAttributeMiddlewaresRunInOnionOrder(): void {
        OnionTrace::$steps = [];

        // CurrentRoute stub pointant sur la fixture annotée (hors convention de routing)
        $route = new class('/onion/edit') extends CurrentRoute {
            public function getController(): string { return OnionController::class; }
            public function getAction(): string     { return 'edit'; }
            public function getMiddlewares(): array  { return []; }
        };

        $manager  = new MiddlewareManager();
        $response = $manager->run($this->makeRequest(), $route, function ($req) {
            OnionTrace::$steps[] = 'controller';
            return 'controller-response';
        });

        $this->assertSame('controller-response', $response);

        // Ordre oignon entrelacé : Alpha (classe) enveloppe Beta (action) qui
        // enveloppe le controller.
        $this->assertSame(
            ['Alpha:before', 'Beta:before', 'controller', 'Beta:after', 'Alpha:after'],
            OnionTrace::$steps
        );
    }
}
