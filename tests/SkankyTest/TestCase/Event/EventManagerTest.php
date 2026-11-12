<?php

namespace SkankyTest\TestCase\Event;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Event\EventManager;
use SkankyDev\Event\MasterEvent;
use SkankyDev\Exception\EventException;
use SkankyDev\Exception\NotFoundException;
use TestApp\Event\OtherHappened;
use TestApp\Event\ThingHappened;
use TestApp\Listener\BrokenListener;
use TestApp\Listener\ThingListener;

class EventManagerTest extends TestCase
{
    protected function setUp(): void {
        $this->resetManager();
        ThingListener::$built = 0;
        Config::set('listeners', []);
    }

    protected function tearDown(): void {
        Config::set('listeners', []);
        $this->resetManager();
    }

    private function resetManager(): void {
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
    }

    public function testEventNameIsItsClass(): void {
        $this->assertEquals(ThingHappened::class, (new ThingHappened())->getName());
    }

    public function testEmitWithoutListenersGivesTheEventBack(): void {
        $event = new ThingHappened();
        $this->assertSame($event, EventManager::_emit($event));
        $this->assertFalse(EventManager::_hasListeners(ThingHappened::class));
    }

    public function testListenCallsTheCallableWithTheEvent(): void {
        EventManager::_listen(ThingHappened::class, function (ThingHappened $e) {
            $e->calls[] = 'closure:' . $e->what;
        });
        $event = EventManager::_emit(new ThingHappened('comment'));
        $this->assertEquals(['closure:comment'], $event->calls);
    }

    public function testListenerOnlyReceivesItsEvent(): void {
        EventManager::_listen(OtherHappened::class, fn($e) => $e->calls[] = 'other');
        $this->assertEquals([], EventManager::_emit(new ThingHappened())->calls);
    }

    public function testConfigListenerIsCalledInOrder(): void {
        Config::set('listeners', [ThingListener::class => true]);
        $event = EventManager::_emit(new ThingHappened('x'));
        $this->assertEquals(['first:x', 'second'], $event->calls);
    }

    public function testConfigListenersRunBeforeManualOnes(): void {
        EventManager::_listen(ThingHappened::class, fn($e) => $e->calls[] = 'manual');
        Config::set('listeners', [ThingListener::class => true]);
        $this->assertEquals(['first:thing', 'second', 'manual'], EventManager::_emit(new ThingHappened())->calls);
    }

    public function testDisabledListenerIsIgnored(): void {
        Config::set('listeners', [ThingListener::class => false]);
        $this->assertEquals([], EventManager::_emit(new ThingHappened())->calls);
        $this->assertFalse(EventManager::_hasListeners(ThingHappened::class));
    }

    public function testListenerIsBuiltLazilyAndOnce(): void {
        Config::set('listeners', [ThingListener::class => true]);
        $this->assertTrue(EventManager::_hasListeners(ThingHappened::class));
        $this->assertEquals(0, ThingListener::$built);

        EventManager::_emit(new ThingHappened());
        EventManager::_emit(new ThingHappened());
        EventManager::_emit(new OtherHappened());
        $this->assertEquals(1, ThingListener::$built);
    }

    public function testCustomNameIsUsedToFindListeners(): void {
        $event = new class extends MasterEvent {
            public bool $seen = false;
            public function getName(): string { return 'custom.name'; }
        };
        EventManager::_listen('custom.name', fn($e) => $e->seen = true);
        $this->assertTrue(EventManager::_emit($event)->seen);
    }

    public function testListenerExceptionGoesUp(): void {
        EventManager::_listen(ThingHappened::class, function () {
            throw new NotFoundException('stop', 404);
        });
        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);
        EventManager::_emit(new ThingHappened());
    }

    public function testUnknownListenerClassThrows(): void {
        Config::set('listeners', ['TestApp\\Listener\\Nope' => true]);
        $this->expectException(EventException::class);
        EventManager::_emit(new ThingHappened());
    }

    public function testListenerWithoutInterfaceThrows(): void {
        Config::set('listeners', [\stdClass::class => true]);
        $this->expectException(EventException::class);
        EventManager::_emit(new ThingHappened());
    }

    public function testListenerWithMissingMethodThrows(): void {
        Config::set('listeners', [BrokenListener::class => true]);
        $this->expectException(EventException::class);
        EventManager::_emit(new ThingHappened());
    }
}
