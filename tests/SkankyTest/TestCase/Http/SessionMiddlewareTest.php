<?php

namespace SkankyTest\TestCase\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Middleware\SessionMiddleware;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;

class SessionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        (new \ReflectionProperty(Router::class,  '_instance'))->setValue(null, null);
        (new \ReflectionProperty(Request::class, '_instance'))->setValue(null, null);
        $_SESSION = [];
        $_SERVER  = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/module/index',
            'REMOTE_ADDR'    => '127.0.0.1',
        ];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        Router::_findCurrentRoute('/module/index');
    }

    public function testHandleCallsNextAndReturnsResult(): void
    {
        $middleware = new SessionMiddleware();
        $request    = new Request();
        $called     = false;

        $result = $middleware->handle($request, function ($req) use (&$called) {
            $called = true;
            return 'response';
        });

        $this->assertTrue($called);
        $this->assertEquals('response', $result);
    }
}
