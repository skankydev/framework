<?php

namespace SkankyTest\TestCase\Http\Middleware;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Middleware\PostOnlyMiddleware;
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;

class PostOnlyMiddlewareTest extends TestCase
{
    protected function setUp(): void {
        $_SESSION = [];
    }

    private function makeRequest(string $method, array $server = []): Request {
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        $_SERVER = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/project/delete/1',
            'REMOTE_ADDR'    => '127.0.0.1',
        ], $server);
        return new Request();
    }

    private function statusOf(Response $r): int {
        return (new \ReflectionProperty($r, 'statusCode'))->getValue($r);
    }

    public function testPostPasses(): void {
        $mw = new PostOnlyMiddleware();
        $this->assertSame('NEXT', $mw->handle($this->makeRequest('POST'), fn($r) => 'NEXT'));
    }

    public function testGetIsBlockedAndRedirects(): void {
        $mw = new PostOnlyMiddleware();
        $called = false;
        $result = $mw->handle(
            $this->makeRequest('GET'),
            function ($r) use (&$called) { $called = true; return 'NEXT'; }
        );
        $this->assertFalse($called, 'le contrôleur ne doit pas être atteint en GET');
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(302, $this->statusOf($result));
    }

    public function testAjaxGetReturns405(): void {
        $mw = new PostOnlyMiddleware();
        $result = $mw->handle(
            $this->makeRequest('GET', ['HTTP_ACCEPT' => 'application/json']),
            fn($r) => 'NEXT'
        );
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(405, $this->statusOf($result));
    }
}
