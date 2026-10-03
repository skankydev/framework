<?php

namespace SkankyTest\TestCase\Http\Middleware;

use PHPUnit\Framework\TestCase;
use SkankyDev\Auth\Auth;
use SkankyDev\Auth\Gates\MasterGate;
use SkankyDev\Config\Config;
use SkankyDev\Http\Middleware\AuthMiddleware;
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;

class FixtureCheckGate extends MasterGate {
    public static bool $authenticated = false;

    protected function resolve(): ?object {
        return self::$authenticated ? new \stdClass() : null;
    }
}

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void {
        FixtureCheckGate::$authenticated = false;

        Config::set('auth.default', 'web');
        Config::set('auth.keepers.web', [
            'gate'     => 'fixture-check',
            'provider' => 'user',
            'redirect' => '/login',
        ]);
        Config::set('auth.providers.user', 'stdClass');
        Config::set('class.gates.fixture-check', FixtureCheckGate::class);

        $ref = new \ReflectionProperty(Auth::class, 'keepers');
        $ref->setValue(null, []);
    }

    private function makeRequest(array $server = []): Request {
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        $_SERVER = array_merge([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/dashboard',
            'REMOTE_ADDR'    => '127.0.0.1',
        ], $server);
        return new Request();
    }

    private function statusOf(Response $response): int {
        $ref = new \ReflectionProperty($response, 'statusCode');
        return $ref->getValue($response);
    }

    private function headerOf(Response $response, string $name): ?string {
        $ref = new \ReflectionProperty($response, 'headers');
        return $ref->getValue($response)[$name] ?? null;
    }

    public function testCallsNextWhenAuthenticated(): void {
        FixtureCheckGate::$authenticated = true;

        $middleware = new AuthMiddleware();
        $called = false;
        $result = $middleware->handle($this->makeRequest(), function ($r) use (&$called) {
            $called = true;
            return 'NEXT';
        });

        $this->assertTrue($called);
        $this->assertSame('NEXT', $result);
    }

    public function testRedirectsWhenNotAuthenticatedAndBrowser(): void {
        $middleware = new AuthMiddleware();
        $result = $middleware->handle($this->makeRequest(), fn($r) => 'NEXT');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(302, $this->statusOf($result));
        $this->assertSame('/login', $this->headerOf($result, 'Location'));
    }

    public function testReturns401WhenNotAuthenticatedAndClientWantsJson(): void {
        $middleware = new AuthMiddleware();
        $request = $this->makeRequest(['HTTP_ACCEPT' => 'application/json']);
        $result = $middleware->handle($request, fn($r) => 'NEXT');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(401, $this->statusOf($result));
    }

    public function testUsesNamedKeeperFromAttributeArgument(): void {
        Config::set('auth.keepers.other', [
            'gate'     => 'fixture-check',
            'provider' => 'user',
            'redirect' => '/other-login',
        ]);

        $middleware = new AuthMiddleware('other');
        $result = $middleware->handle($this->makeRequest(), fn($r) => 'NEXT');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('/other-login', $this->headerOf($result, 'Location'));
    }
}
