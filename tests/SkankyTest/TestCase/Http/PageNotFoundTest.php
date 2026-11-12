<?php

namespace SkankyTest\TestCase\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Event\EventManager;
use SkankyDev\Exception\NotFoundException;
use SkankyDev\Http\Event\PageNotFound;
use SkankyDev\Http\Request;

class PageNotFoundTest extends TestCase
{
    private array $events = [];

    protected function setUp(): void {
        (new \ReflectionProperty(Request::class, '_instance'))->setValue(null, null);
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
        $_SERVER['REQUEST_URI'] = '/nulle-part';
        $this->events = [];
        EventManager::_listen(PageNotFound::class, fn($e) => $this->events[] = $e);
    }

    protected function tearDown(): void {
        (new \ReflectionProperty(Request::class, '_instance'))->setValue(null, null);
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
    }

    public function testNotFoundEmitsPageNotFound(): void {
        notFound();
        $this->assertCount(1, $this->events);
        $this->assertEquals('/nulle-part', $this->events[0]->uri);
        $this->assertNull($this->events[0]->exception);
    }

    public function testNotFoundPassesTheException(): void {
        $e = new NotFoundException('nope', 404);
        notFound([], $e);
        $this->assertSame($e, $this->events[0]->exception);
    }
}
