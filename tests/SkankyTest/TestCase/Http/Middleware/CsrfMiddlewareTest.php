<?php

namespace SkankyTest\TestCase\Http\Middleware;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Middleware\CsrfMiddleware;
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;

class CsrfMiddlewareTest extends TestCase
{
    protected function setUp(): void {
        $_SESSION = ['csrf_token' => 'valid-token'];
    }

    private function makeRequest(string $method, array $post = [], array $server = []): Request {
        $_GET = [];
        $_POST = $post;
        $_COOKIE = $_FILES = [];
        $_SERVER = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/task/setStatus/1',
            'REMOTE_ADDR'    => '127.0.0.1',
        ], $server);
        return new Request();
    }

    private function statusOf(Response $response): int {
        $ref = new \ReflectionProperty($response, 'statusCode');
        return $ref->getValue($response);
    }

    public function testGetPassesThroughWithoutToken(): void {
        $mw = new CsrfMiddleware();
        $result = $mw->handle($this->makeRequest('GET'), fn($r) => 'NEXT');
        $this->assertSame('NEXT', $result);
    }

    public function testPostWithValidFieldTokenPasses(): void {
        $mw = new CsrfMiddleware();
        $result = $mw->handle($this->makeRequest('POST', ['_token' => 'valid-token']), fn($r) => 'NEXT');
        $this->assertSame('NEXT', $result);
    }

    public function testPostWithValidHeaderTokenPasses(): void {
        $mw = new CsrfMiddleware();
        $request = $this->makeRequest('POST', [], ['HTTP_X_CSRF_TOKEN' => 'valid-token']);
        $result = $mw->handle($request, fn($r) => 'NEXT');
        $this->assertSame('NEXT', $result);
    }

    public function testPostWithWrongTokenIsBlocked(): void {
        $mw = new CsrfMiddleware();
        $called = false;
        $result = $mw->handle(
            $this->makeRequest('POST', ['_token' => 'WRONG']),
            function ($r) use (&$called) { $called = true; return 'NEXT'; }
        );
        $this->assertFalse($called, 'le contrôleur ne doit pas être atteint');
        $this->assertInstanceOf(Response::class, $result);
    }

    public function testPostWithMissingTokenIsBlocked(): void {
        $mw = new CsrfMiddleware();
        $result = $mw->handle($this->makeRequest('POST'), fn($r) => 'NEXT');
        $this->assertInstanceOf(Response::class, $result);
    }

    public function testAjaxFailureReturns419(): void {
        $mw = new CsrfMiddleware();
        $request = $this->makeRequest('POST', ['_token' => 'WRONG'], ['HTTP_ACCEPT' => 'application/json']);
        $result = $mw->handle($request, fn($r) => 'NEXT');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(419, $this->statusOf($result));
    }

    public function testBrowserFailureRedirects(): void {
        $mw = new CsrfMiddleware();
        $request = $this->makeRequest('POST', ['_token' => 'WRONG'], ['HTTP_REFERER' => '/project/show/1']);
        $result = $mw->handle($request, fn($r) => 'NEXT');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(302, $this->statusOf($result));
    }
}
