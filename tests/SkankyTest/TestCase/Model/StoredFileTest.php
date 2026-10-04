<?php

namespace SkankyTest\TestCase\Model;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Request;
use SkankyDev\Model\Document\EmbeddedDocument;
use SkankyDev\Model\Document\StoredFile;

class StoredFileTest extends TestCase
{
    private function make(array $data = []): StoredFile {
        return new StoredFile([
            'path'          => 'img/abc.png',
            'original_name' => 'photo.png',
            'mime'          => 'image/png',
            'size'          => 1234,
            'width'         => 3,
            'height'        => 2,
            ...$data,
        ]);
    }

    public function testIsAnEmbeddedDocument(): void {
        $this->assertInstanceOf(EmbeddedDocument::class, $this->make());
    }

    public function testRelativeUrlIsThePath(): void {
        $this->assertEquals('/upload/img/abc.png', $this->make()->url());
    }

    public function testAbsoluteUrlUsesCurrentRequest(): void {
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST']      = 'skankydev.com';

        $this->assertEquals('https://skankydev.com/upload/img/abc.png', $this->make()->url(true));
        $ref->setValue(null, null);
    }

    public function testFullPathIsUnderUploadFolder(): void {
        $this->assertEquals(\SkankyDev\Config\Config::get('upload.folder') . DS . 'img' . DS . 'abc.png', $this->make()->fullPath());
    }

    public function testHelpers(): void {
        $file = $this->make();
        $this->assertTrue($file->isImage());
        $this->assertEquals('png', $file->extension());
        $this->assertFalse($this->make(['mime' => 'application/pdf'])->isImage());
    }

    public function testJsonContainsComputedUrl(): void {
        $json = json_decode(json_encode($this->make()), true);
        $this->assertEquals('/upload/img/abc.png', $json['url']);
        $this->assertEquals('photo.png', $json['original_name']);
    }

    public function testBsonRoundTripKeepsTheType(): void {
        $bson = \MongoDB\BSON\Document::fromPHP(['file' => $this->make()]);
        $back = $bson->toPHP();
        $this->assertInstanceOf(StoredFile::class, $back->file);
        $this->assertEquals(1234, $back->file->size);
        $this->assertEquals('/upload/img/abc.png', $back->file->url());
    }

    public function testDeleteRemovesFile(): void {
        $file = $this->make(['path' => 'stored_file_test.txt']);
        @mkdir(dirname($file->fullPath()), 0775, true);
        file_put_contents($file->fullPath(), 'x');

        $this->assertTrue($file->delete());
        $this->assertFileDoesNotExist($file->fullPath());
        $this->assertFalse($file->delete());
    }
}
