<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Traits\ArrayPathable;

class ArrayPathableTestHelper {
    use ArrayPathable;

    public array $data = [];

    public function get(string $path = ''): mixed {
        return self::arrayGet($path, $this->data);
    }
    public function set(string $path, mixed $value): bool {
        return self::arraySet($path, $value, $this->data);
    }
    public function delete(string $path): bool|null {
        return self::arrayDelete($path, $this->data);
    }
}

class ArrayPathableTest extends TestCase
{
    private ArrayPathableTestHelper $arr;

    protected function setUp(): void {
        $this->arr = new ArrayPathableTestHelper();
    }

    public function testSetInArray(): void {
        $this->arr->set('coucou.test', 10);
        $this->arr->set('youpi', 'lala');

        $expected = [
            'coucou' => ['test' => 10],
            'youpi'  => 'lala',
        ];
        $this->assertArrayHasKey('youpi', $this->arr->data);
        $this->assertEquals($expected, $this->arr->get());
        // Missing key returns null, not false
        $this->assertNull($this->arr->get('missing'));
    }

    public function testGetInArray(): void {
        $this->arr->set('coucou.test', 10);
        $this->arr->set('coucou.test2', 20);
        $this->arr->set('coucou.test2', 30); // overwrite
        $this->arr->set('youpi', 'lala');

        $this->assertEquals(['test' => 10, 'test2' => 30], $this->arr->get('coucou'));
        $this->assertEquals(30, $this->arr->get('coucou.test2'));
        $this->assertNull($this->arr->get('does.not.exist'));
    }

    public function testGetFullArrayWhenPathEmpty(): void {
        $this->arr->set('a', 1);
        $this->arr->set('b', 2);
        $this->assertEquals(['a' => 1, 'b' => 2], $this->arr->get());
    }

    public function testDeleteInArray(): void {
        $this->arr->set('coucou.test', 10);
        $this->arr->set('coucou.test2', 20);
        $this->arr->set('youpi', 'lala');

        $this->arr->delete('coucou.test2');
        $this->assertEquals(['coucou' => ['test' => 10], 'youpi' => 'lala'], $this->arr->get());
        $this->assertEquals(10, $this->arr->get('coucou.test'));
        // Deleting a non-existent nested key returns null
        $this->assertNull($this->arr->delete('coucou.youpi.test'));
    }

    public function testSetCreatesIntermediateArrays(): void {
        $this->arr->set('a.b.c', 'deep');
        $this->assertEquals('deep', $this->arr->get('a.b.c'));
    }
}
