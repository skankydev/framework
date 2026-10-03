<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Session;

class SessionTest extends TestCase
{
    protected function setUp(): void {
        $_SESSION = [];
    }

    // ── get / set ─────────────────────────────────────────────────────────────

    public function testSetAndGet(): void {
        Session::set('user.name', 'Simon');
        $this->assertEquals('Simon', Session::get('user.name'));
    }

    public function testGetFullSessionWhenNoPath(): void {
        $_SESSION = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], Session::get());
    }

    public function testGetReturnsNullForMissingKey(): void {
        $this->assertNull(Session::get('missing'));
    }

    public function testSetCreatesNestedKeys(): void {
        Session::set('a.b.c', 'deep');
        $this->assertEquals('deep', Session::get('a.b.c'));
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteRemovesKey(): void {
        Session::set('temp', 'value');
        Session::delete('temp');
        $this->assertNull(Session::get('temp'));
    }

    public function testDeleteNestedKey(): void {
        Session::set('user.role', 'admin');
        Session::delete('user.role');
        $this->assertNull(Session::get('user.role'));
    }

    // ── insert ────────────────────────────────────────────────────────────────

    public function testInsertAppendsToArray(): void {
        Session::insert('items', 'first');
        Session::insert('items', 'second');
        $this->assertEquals(['first', 'second'], Session::get('items'));
    }

    public function testInsertCreatesArrayWhenKeyMissing(): void {
        Session::insert('new_list', 'hello');
        $this->assertEquals(['hello'], Session::get('new_list'));
    }

    public function testInsertReturnsFalseWhenValueIsNotArray(): void {
        Session::set('scalar', 'oops');
        $result = Session::insert('scalar', 'item');
        $this->assertFalse($result);
    }

    // ── getAndClean ───────────────────────────────────────────────────────────

    public function testGetAndCleanReturnsValueThenDeletesIt(): void {
        Session::set('flash.error', 'Something went wrong');
        $value = Session::getAndClean('flash.error');
        $this->assertEquals('Something went wrong', $value);
        $this->assertNull(Session::get('flash.error'));
    }

    public function testGetAndCleanReturnsNullOnMissingKey(): void {
        $this->assertNull(Session::getAndClean('nonexistent'));
    }

    // ── start / destroy ───────────────────────────────────────────────────────

    public function testStartDoesNotThrow(): void
    {
        // session_start() en mode CLI peut émettre une notice mais ne doit pas planter
        @Session::start();
        $this->assertTrue(true);
    }

    public function testDestroyDoesNotThrow(): void
    {
        @Session::destroy();
        $this->assertTrue(true);
    }

    // ── regenerate ────────────────────────────────────────────────────────────

    public function testRegenerateDoesNotThrowWithoutActiveSession(): void
    {
        // Pas de session active en CLI/tests -> no-op silencieux, pas de warning.
        Session::regenerate();
        $this->assertTrue(true);
    }
}
