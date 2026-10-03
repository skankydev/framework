<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Traits\StringFacility;

class StringFacilityTestHelper {
    use StringFacility;
}

class StringFacilityTest extends TestCase
{
    private StringFacilityTestHelper $str;

    protected function setUp(): void {
        $this->str = new StringFacilityTestHelper();
    }

    public function testToDash(): void {
        $this->assertEquals('info-action-name', $this->str->toDash('infoActionName'));
        $this->assertEquals('info_action_name', $this->str->toDash('infoActionName', '_'));
        $this->assertEquals('info.action.name', $this->str->toDash('infoActionName', '.'));
    }

    public function testToCap(): void {
        $this->assertEquals('InfoActionName', $this->str->toCap('info-action-name'));
        $this->assertEquals('InfoActionName', $this->str->toCap('info_action_name', '_'));
        $this->assertEquals('InfoActionName', $this->str->toCap('info.action.name', '.'));
    }

    public function testToCamel(): void {
        $this->assertEquals('infoActionName', $this->str->toCamel('info-action-name'));
        $this->assertEquals('infoActionName', $this->str->toCamel('info_action_name', '_'));
        $this->assertEquals('infoActionName', $this->str->toCamel('info.action.name', '.'));
    }

    public function testCleanString(): void {
        $this->assertEquals(
            'AAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiieooooouuuuyy',
            $this->str->cleanString('ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ')
        );
        $this->assertEquals(
            'nomDeFichierbizar.txt',
            $this->str->cleanString('ñomDéFîchìèr<bizar>.txt')
        );
    }

    public function testPluralize(): void {
        $this->assertEquals('modules', $this->str->pluralize('module'));
        $this->assertEquals('categories', $this->str->pluralize('category'));
        $this->assertEquals('boxes', $this->str->pluralize('box'));
    }

    public function testSingularize(): void {
        $this->assertEquals('module', $this->str->singularize('modules'));
        $this->assertEquals('category', $this->str->singularize('categories'));
        $this->assertEquals('child', $this->str->singularize('children'));
    }

    public function testToHuman(): void {
        $this->assertEquals('My Field Name', $this->str->toHuman('my_field-name'));
        $this->assertEquals('Module Type', $this->str->toHuman('module_type'));
    }

    public function testDotToFolder(): void {
        $expected = implode(DIRECTORY_SEPARATOR, ['module', 'part', 'scenario']);
        $this->assertEquals($expected, $this->str->dotToFolder('module.part.scenario'));
        $this->assertEquals('single', $this->str->dotToFolder('single'));
    }

    public function testPreserveCase(): void {
        $this->assertEquals('Child', $this->str->preserveCase('Children', 'child'));
        $this->assertEquals('child', $this->str->preserveCase('children', 'child'));
    }
}
