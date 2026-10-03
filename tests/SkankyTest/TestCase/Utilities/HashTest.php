<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Hash;

class HashTest extends TestCase
{
    public function testMakeProducesVerifiableHash(): void {
        $hash = Hash::make('correct-password');
        $this->assertNotSame('correct-password', $hash);
        $this->assertTrue(Hash::check('correct-password', $hash));
    }

    public function testCheckFailsOnWrongValue(): void {
        $hash = Hash::make('correct-password');
        $this->assertFalse(Hash::check('wrong-password', $hash));
    }

    public function testMakeUsesArgon2idWhenAvailable(): void {
        $hash = Hash::make('secret');
        if (in_array('argon2id', password_algos(), true)) {
            $this->assertStringStartsWith('$argon2id$', $hash);
        } else {
            $this->assertTrue(password_verify('secret', $hash));
        }
    }

    public function testNeedsRehashIsFalseForFreshHash(): void {
        $hash = Hash::make('secret');
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function testNeedsRehashIsTrueForOutdatedAlgo(): void {
        // Un bcrypt à coût 4 est toujours "à re-hasher" quel que soit l'algo courant
        // (soit parce que l'algo a changé pour argon2id, soit à cause du coût trop bas).
        $oldHash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertTrue(Hash::needsRehash($oldHash));
    }

    public function testHashTokenIsDeterministicSha256(): void {
        $token = 'abc123';
        $hashed = Hash::hashToken($token);
        $this->assertSame(hash('sha256', $token), $hashed);
        $this->assertSame(64, strlen($hashed));
    }

    public function testHashTokenDiffersForDifferentTokens(): void {
        $this->assertNotSame(Hash::hashToken('a'), Hash::hashToken('b'));
    }
}
