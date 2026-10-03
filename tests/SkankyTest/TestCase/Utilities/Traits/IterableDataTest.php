<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Traits\IterableData;
use Iterator;

class IterableDataTestHelper implements Iterator {
    use IterableData;
    public array $data = [];
}

class IterableDataTest extends TestCase
{
    public function testForeachIteratesAllItems(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = [];
        foreach ($obj as $key => $value) {
            $result[$key] = $value;
        }
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    public function testRewindResetsToFirst(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = [10, 20, 30];

        // consume all
        foreach ($obj as $v) {}

        $obj->rewind();
        $this->assertEquals(10, $obj->current());
    }

    public function testEmptyDataIsNotValid(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = [];
        $obj->rewind();
        $this->assertFalse($obj->valid());
    }

    public function testCurrentAndKey(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = ['x' => 42];
        $obj->rewind();
        $this->assertEquals('x', $obj->key());
        $this->assertEquals(42, $obj->current());
    }

    public function testNext(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = [1, 2, 3];
        $obj->rewind();
        $obj->next();
        $this->assertEquals(2, $obj->current());
    }

    public function testCanIterateTwice(): void {
        $obj = new IterableDataTestHelper();
        $obj->data = [1, 2, 3];

        $first  = iterator_to_array($obj);
        $second = iterator_to_array($obj);
        $this->assertEquals($first, $second);
    }
}
