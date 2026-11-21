<?php

namespace SkankyTest\TestCase\Core;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Core\Application;
use SkankyDev\Core\ExceptionHandler;
use SkankyDev\Event\EventManager;
use SkankyDev\Http\Event\ResponseSent;
use SkankyDev\Http\Request;
use SkankyDev\Http\Response;
use SkankyDev\Utilities\Log;

/**
 * afterResponse() only: run() needs routes and a whole app around it.
 */
class ApplicationTest extends TestCase
{
    protected function setUp(): void {
        $this->resetManager();
        Config::set('listeners', []);
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1'];
    }

    protected function tearDown(): void {
        $this->resetManager();
    }

    private function resetManager(): void {
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
    }

    /** An Application without its constructor (no initConf, no global handler). */
    private function app(): Application {
        $app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(Application::class, 'exceptionHandler'))->setValue($app, new ExceptionHandler(false));
        return $app;
    }

    private function afterResponse(Application $app, Request $request, Response $response): void {
        (new \ReflectionMethod(Application::class, 'afterResponse'))->invoke($app, $request, $response);
    }

    public function testEmitsResponseSentWithTheRequestAndTheResponse(): void {
        $received = null;
        EventManager::_listen(ResponseSent::class, function (ResponseSent $e) use (&$received) {
            $received = $e;
        });
        $request  = new Request();
        $response = new Response();

        $this->afterResponse($this->app(), $request, $response);

        $this->assertInstanceOf(ResponseSent::class, $received);
        $this->assertSame($request, $received->request);
        $this->assertSame($response, $received->response);
    }

    public function testAListenerErrorIsLoggedNotThrown(): void {
        EventManager::_listen(ResponseSent::class, function () {
            throw new \RuntimeException('stats are down');
        });
        $logDir = Log::folder();
        $before = is_dir($logDir) ? count(glob($logDir . '/*.log')) : 0;

        $this->afterResponse($this->app(), new Request(), new Response());

        // no exception reached us; something was written in the logs
        $logs = is_dir($logDir) ? glob($logDir . '/*.log') : [];
        $this->assertNotEmpty($logs);
        $found = false;
        foreach ($logs as $file) {
            $found = $found || str_contains(file_get_contents($file), 'stats are down');
        }
        $this->assertTrue($found, "the listener's exception should be logged ($before log files before)");
    }
}
