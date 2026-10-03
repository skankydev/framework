<?php

namespace SkankyTest\TestCase\Model;

use PHPUnit\Framework\TestCase;
use SkankyDev\Model\Behavior\MasterBehavior;
use SkankyDev\Model\Behavior\TimedBehavior;

class MasterBehaviorTest extends TestCase
{
    // ── MasterBehavior — tous les hooks sont des no-op ────────────────────────

    public function testAllHooksRunWithoutException(): void
    {
        $behavior = new MasterBehavior();
        $doc      = new \stdClass();

        $behavior->beforeInsert($doc);
        $behavior->afterInsert($doc);
        $behavior->beforeUpdate($doc);
        $behavior->afterUpdate($doc);
        $behavior->beforeCreateEntity($doc);
        $behavior->afterCreateEntity($doc);

        $this->assertTrue(true); // si on arrive ici, aucun hook n'a lancé d'exception
    }

    // ── TimedBehavior ─────────────────────────────────────────────────────────

    public function testTimedBeforeInsertSetsBothTimestamps(): void
    {
        $behavior = new TimedBehavior();
        $doc      = new \stdClass();

        $behavior->beforeInsert($doc);

        $this->assertInstanceOf(\DateTime::class, $doc->created_at);
        $this->assertInstanceOf(\DateTime::class, $doc->updated_at);
    }

    public function testTimedBeforeUpdateSetsUpdatedAt(): void
    {
        $behavior = new TimedBehavior();
        $doc      = new \stdClass();

        $behavior->beforeUpdate($doc);

        $this->assertInstanceOf(\DateTime::class, $doc->updated_at);
        $this->assertFalse(isset($doc->created_at));
    }
}
