<?php

namespace SkankyTest\TestCase\Validation\Rules;

use PHPUnit\Framework\TestCase;
use SkankyDev\Validation\Rules\Unique;

/**
 * Collection factice (Singleton minimal) pour tester Unique sans MongoDB.
 */
class FixtureUniqueCollection {
    private static ?self $instance = null;
    public array $records = [];

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void {
        self::$instance = new self();
    }

    public function findOne(array $filter): ?object {
        foreach ($this->records as $record) {
            foreach ($filter as $key => $value) {
                if (!isset($record->$key) || $record->$key !== $value) {
                    continue 2;
                }
            }
            return $record;
        }
        return null;
    }
}

class UniqueTest extends TestCase
{
    protected function setUp(): void {
        FixtureUniqueCollection::reset();
    }

    public function testPassesWhenNoMatchingRecord(): void {
        $rule = new Unique(FixtureUniqueCollection::class);
        $this->assertTrue($rule->check('email', 'new@skankydev.com'));
    }

    public function testFailsWhenValueAlreadyExists(): void {
        $existing = new \stdClass();
        $existing->email = 'taken@skankydev.com';
        FixtureUniqueCollection::getInstance()->records[] = $existing;

        $rule = new Unique(FixtureUniqueCollection::class);
        $this->assertFalse($rule->check('email', 'taken@skankydev.com'));
    }

    public function testUsesCustomColumnWhenGiven(): void {
        $existing = new \stdClass();
        $existing->slug = 'my-slug';
        FixtureUniqueCollection::getInstance()->records[] = $existing;

        $rule = new Unique(FixtureUniqueCollection::class, 'slug');
        $this->assertFalse($rule->check('field_name', 'my-slug'));
        $this->assertTrue($rule->check('field_name', 'other-slug'));
    }

    public function testPassesWhenMatchIsTheExceptedRecordItself(): void {
        $existing = new \stdClass();
        $existing->_id = 'u1';
        $existing->email = 'simon@skankydev.com';
        FixtureUniqueCollection::getInstance()->records[] = $existing;

        // Édition de u1 sans changer son email -> ne doit pas se rejeter lui-même
        $rule = new Unique(FixtureUniqueCollection::class, null, 'u1');
        $this->assertTrue($rule->check('email', 'simon@skankydev.com'));
    }

    public function testStillFailsWhenMatchIsADifferentRecord(): void {
        $existing = new \stdClass();
        $existing->_id = 'u2';
        $existing->email = 'taken@skankydev.com';
        FixtureUniqueCollection::getInstance()->records[] = $existing;

        // Édition de u1, mais l'email appartient à u2 -> toujours refusé
        $rule = new Unique(FixtureUniqueCollection::class, null, 'u1');
        $this->assertFalse($rule->check('email', 'taken@skankydev.com'));
    }

    public function testEmptyValuePassesByDefault(): void {
        $rule = new Unique(FixtureUniqueCollection::class);
        $this->assertTrue($rule->check('email', ''));
        $this->assertTrue($rule->check('email', null));
    }

    public function testThrowsOnUnknownCollectionClass(): void {
        $rule = new Unique('App\\Does\\Not\\Exist');
        $this->expectException(\Exception::class);
        $rule->check('email', 'x@x.com');
    }

    public function testMessageMentionsField(): void {
        $rule = new Unique(FixtureUniqueCollection::class);
        $this->assertStringContainsString('email', $rule->message('email'));
    }
}
