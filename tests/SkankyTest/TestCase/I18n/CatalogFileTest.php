<?php

namespace SkankyTest\TestCase\I18n;

use PHPUnit\Framework\TestCase;
use SkankyDev\I18n\CatalogFile;

class CatalogFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void {
        $this->dir = sys_get_temp_dir() . '/skankydev_catalog_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void {
        foreach (glob($this->dir . '/*/*.php') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            is_dir($file) ? rmdir($file) : unlink($file);
        }
        rmdir($this->dir);
    }

    private function write(string $content): string {
        $path = $this->dir . '/blog.php';
        file_put_contents($path, $content);
        return $path;
    }

    public function testMissingFileIsEmpty(): void {
        $file = new CatalogFile($this->dir . '/absent.php');
        $this->assertEquals([], $file->data);
    }

    public function testInvalidFileThrows(): void {
        $this->expectException(\UnexpectedValueException::class);
        new CatalogFile($this->write("<?php return 'nope';"));
    }

    public function testHasSetAndConflict(): void {
        $file = new CatalogFile($this->write("<?php return ['post' => ['title' => 'Titre', 'new' => null], 'label' => 'x'];"));

        $this->assertTrue($file->has('post.title'));
        $this->assertTrue($file->has('post.new'));          // null compte comme présent
        $this->assertFalse($file->has('post.nope'));
        $this->assertFalse($file->has('label.sub'));

        $this->assertTrue($file->set('post.count', null));
        $this->assertTrue($file->set('other.deep.key', 'v'));
        $this->assertFalse($file->set('label.sub', null));  // label est un message
        $this->assertEquals('x', $file->data['label']);
    }

    public function testLeavesAndUntranslated(): void {
        $file = new CatalogFile($this->write("<?php return ['post' => ['title' => 'Titre', 'new' => null], 'empty' => []];"));
        $this->assertEquals(['post.title' => 'Titre', 'post.new' => null], $file->leaves());
        $this->assertEquals(['post.new'], $file->untranslated());
    }

    public function testRemoveCleansEmptyGroups(): void {
        $file = new CatalogFile($this->write("<?php return ['a' => ['b' => ['c' => 'x']], 'd' => 'y'];"));
        $file->remove('a.b.c');
        $this->assertEquals(['d' => 'y'], $file->data);
        $file->remove('nope.key');
        $this->assertEquals(['d' => 'y'], $file->data);
    }

    public function testExportFormat(): void {
        $expected = "[\n\t'label' => 'L’article d\\'Ana',\n\t'post'  => [\n\t\t'title' => null,\n\t],\n\t'list'  => [],\n]";
        $this->assertEquals($expected, CatalogFile::export([
            'label' => "L’article d'Ana",
            'post'  => ['title' => null],
            'list'  => [],
        ]));
    }

    public function testSaveKeepsHeaderAndRoundTrips(): void {
        $path = $this->write("<?php\n/**\n * Mon en-tête, qui parle de return\n */\n\n// commentaire perdu\nreturn ['b' => 'B', 'a' => ['x' => 'X']];\n");
        $file = new CatalogFile($path);
        $file->set('a.y', null);
        $file->save();

        $source = file_get_contents($path);
        $this->assertStringStartsWith("<?php\n/**\n * Mon en-tête, qui parle de return\n */\n\n// commentaire perdu\nreturn [", $source);
        $this->assertEquals(['b' => 'B', 'a' => ['x' => 'X', 'y' => null]], require $path);
    }

    public function testSaveCreatesFolderAndDefaultHeader(): void {
        $file = new CatalogFile($this->dir . '/fr/app.php');
        $file->set('welcome', null);
        $file->save();
        $this->assertStringStartsWith("<?php\n\nreturn [", file_get_contents($this->dir . '/fr/app.php'));
    }
}
