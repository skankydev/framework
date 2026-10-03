<?php

namespace SkankyTest\TestCase\Auth;

use PHPUnit\Framework\TestCase;
use SkankyDev\Auth\Auth;
use SkankyDev\Auth\Gates\MasterGate;
use SkankyDev\Config\Config;

class FixtureAuthGate extends MasterGate {
    protected function resolve(): ?object {
        return null;
    }
}

class AuthTest extends TestCase
{
    protected function setUp(): void {
        Config::set('auth.default', 'web');
        Config::set('auth.keepers.web', [
            'gate'     => 'fixture',
            'provider' => 'user',
        ]);
        Config::set('auth.providers.user', 'stdClass');
        Config::set('class.gates.fixture', FixtureAuthGate::class);

        // Le cache statique des keepers doit repartir de zéro entre chaque test.
        $ref = new \ReflectionProperty(Auth::class, 'keepers');
        $ref->setAccessible(true);
        $ref->setValue(null, []);
    }

    public function testKeeperResolvesFromConfig(): void {
        $this->assertInstanceOf(FixtureAuthGate::class, Auth::keeper());
    }

    public function testKeeperWithoutNameUsesDefault(): void {
        $this->assertSame(Auth::keeper(), Auth::keeper('web'));
    }

    public function testKeeperIsCachedAcrossCalls(): void {
        $this->assertSame(Auth::keeper('web'), Auth::keeper('web'));
    }

    public function testKeeperThrowsOnUnknownKeeperName(): void {
        $this->expectException(\Exception::class);
        Auth::keeper('does-not-exist');
    }

    public function testKeeperThrowsOnUnknownGate(): void {
        Config::set('auth.keepers.broken', ['gate' => 'missing', 'provider' => 'user']);
        $this->expectException(\Exception::class);
        Auth::keeper('broken');
    }

    public function testKeeperThrowsOnUnknownProvider(): void {
        Config::set('auth.keepers.broken2', ['gate' => 'fixture', 'provider' => 'missing']);
        $this->expectException(\Exception::class);
        Auth::keeper('broken2');
    }

    public function testFacadeDelegatesToDefaultKeeper(): void {
        $this->assertFalse(Auth::check());
        $this->assertNull(Auth::user());
        $this->assertNull(Auth::id());
    }
}
