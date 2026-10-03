<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyDev\Exception\UnknownMethodException;

class SingletonSubject {
    use Singleton;

    public string $value = 'default';

    public function getValue(): string {
        return $this->value;
    }

    public function setValue(string $v): void {
        $this->value = $v;
    }
}

class SingletonTest extends TestCase
{
    protected function setUp(): void {
        $ref = new \ReflectionProperty(SingletonSubject::class, '_instance');
        $ref->setValue(null, null);
    }

    public function testGetInstanceReturnsSameObject(): void {
        $a = SingletonSubject::getInstance();
        $b = SingletonSubject::getInstance();
        $this->assertSame($a, $b);
    }

    public function testCallStaticForwardsToInstance(): void {
        $this->assertEquals('default', SingletonSubject::_getValue());
    }

    public function testCallStaticStripsLeadingUnderscore(): void {
        SingletonSubject::_setValue('changed');
        $this->assertEquals('changed', SingletonSubject::getInstance()->getValue());
    }

    public function testCallStaticThrowsOnUnknownMethod(): void {
        $this->expectException(UnknownMethodException::class);
        SingletonSubject::_nonExistentMethod();
    }
}
