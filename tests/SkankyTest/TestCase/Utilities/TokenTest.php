<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Token;

class TokenTest extends TestCase
{
    public function testCreateToken(): void {
        $token = new Token();
        $this->assertTrue(property_exists($token, 'value'));
        $this->assertTrue(property_exists($token, 'time'));
        // value is a 32-char hex string (16 random bytes)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token->value);
        $this->assertIsInt($token->time);
    }

    public function testCheckValue(): void {
        $token = new Token();
        $this->assertTrue($token->checkValue($token->value));
        $this->assertFalse($token->checkValue('not-the-right-value'));
        $this->assertFalse($token->checkValue(''));
    }

    public function testCheckTime(): void {
        $token = new Token();
        $this->assertTrue($token->checkTime(3600));   // valid for 1 hour
        $this->assertFalse($token->checkTime(-1));    // already expired
    }

    public function testGetToken(): void {
        $token = new Token();
        $this->assertEquals($token->value, $token->getToken());
    }

    public function testTwoTokensAreUnique(): void {
        $a = new Token();
        $b = new Token();
        $this->assertNotEquals($a->value, $b->value);
    }

    public function testIsEmbeddable(): void {
        $token = new Token();
        $this->assertInstanceOf(\SkankyDev\Model\Document\EmbeddedDocument::class, $token);
        $this->assertInstanceOf(\MongoDB\BSON\Persistable::class, $token);
    }

    public function testBsonRoundTrip(): void {
        $token = new Token();
        $bson = $token->bsonSerialize();

        $restored = new Token();
        $restored->bsonUnserialize($bson + ['__pclass' => 'ignored']);

        $this->assertSame($token->value, $restored->value);
        $this->assertSame($token->time, $restored->time);
    }
}
