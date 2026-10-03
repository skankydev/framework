<?php

namespace SkankyTest\TestCase\Auth\Gates;

use PHPUnit\Framework\TestCase;
use SkankyDev\Auth\Gates\MasterGate;

class FixtureGate extends MasterGate {
    public ?object $toResolve = null;

    protected function resolve(): ?object {
        return $this->toResolve;
    }
}

class MasterGateTest extends TestCase
{
    public function testUserIsResolvedOnceAndCached(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $user = new \stdClass();
        $user->_id = 'abc';
        $gate->toResolve = $user;

        $this->assertSame($user, $gate->user());

        // Changer toResolve après coup ne doit rien changer : résolu une seule fois
        $gate->toResolve = null;
        $this->assertSame($user, $gate->user());
    }

    public function testCheckReflectsUserPresence(): void {
        $gateNoUser = new FixtureGate([], 'SomeProvider');
        $this->assertFalse($gateNoUser->check());

        $gateWithUser = new FixtureGate([], 'SomeProvider');
        $gateWithUser->toResolve = new \stdClass();
        $this->assertTrue($gateWithUser->check());
    }

    public function testIdReturnsUserId(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $user = new \stdClass();
        $user->_id = 'xyz';
        $gate->toResolve = $user;

        $this->assertEquals('xyz', $gate->id());
    }

    public function testIdReturnsNullWhenNoUser(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $this->assertNull($gate->id());
    }

    public function testLoginInvalidatesCache(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $first = new \stdClass();
        $gate->toResolve = $first;
        $this->assertSame($first, $gate->user());

        $second = new \stdClass();
        $gate->toResolve = $second;
        $gate->login($second);

        $this->assertSame($second, $gate->user());
    }

    public function testLogoutInvalidatesCache(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $gate->toResolve = new \stdClass();
        $this->assertNotNull($gate->user());

        $gate->toResolve = null;
        $gate->logout();

        $this->assertNull($gate->user());
    }

    public function testAttemptDefaultsToFalse(): void {
        $gate = new FixtureGate([], 'SomeProvider');
        $this->assertFalse($gate->attempt(['email' => 'a@a.com', 'password' => 'x']));
    }
}
