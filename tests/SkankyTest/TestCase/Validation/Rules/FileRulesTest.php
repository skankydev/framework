<?php

namespace SkankyTest\TestCase\Validation\Rules;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\UploadedFile;
use SkankyDev\Validation\Rules\File;
use SkankyDev\Validation\Rules\Image;
use SkankyDev\Validation\Rules\MaxSize;
use SkankyDev\Validation\Rules\Mimes;
use SkankyDev\Validation\Validator;

class FileRulesTest extends TestCase
{
    private array $tmpFiles = [];

    protected function tearDown(): void {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    private function png(): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'rule');
        imagepng(imagecreatetruecolor(2, 2), $path);
        $this->tmpFiles[] = $path;
        return new UploadedFile('a.png', $path, UPLOAD_ERR_OK, filesize($path), test: true);
    }

    private function text(int $size = 5): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'rule');
        file_put_contents($path, str_repeat('a', $size));
        $this->tmpFiles[] = $path;
        return new UploadedFile('a.jpg', $path, UPLOAD_ERR_OK, $size, test: true);
    }

    public function testEmptyValuePassesEveryFileRule(): void {
        foreach ([new File(), new Image(), new Mimes('png'), new MaxSize(1)] as $rule) {
            $this->assertTrue($rule->check('img', null));
        }
    }

    public function testFileRule(): void {
        $rule = new File();
        $this->assertTrue($rule->check('img', $this->png()));
        $this->assertFalse($rule->check('img', 'not a file'));
    }

    public function testFileRuleReportsUploadError(): void {
        $rule = new File();
        $this->assertFalse($rule->check('img', new UploadedFile('a.png', '', UPLOAD_ERR_INI_SIZE)));
        $this->assertStringContainsString('taille maximale', $rule->message('img'));
    }

    public function testImageRuleChecksContent(): void {
        $rule = new Image();
        $this->assertTrue($rule->check('img', $this->png()));
        // .jpg name but text content
        $this->assertFalse($rule->check('img', $this->text()));
    }

    public function testMimesRuleUsesRealType(): void {
        $this->assertTrue((new Mimes('jpg', 'png'))->check('img', $this->png()));
        $this->assertFalse((new Mimes('jpg', 'webp'))->check('img', $this->png()));
        // client name says .jpg, content is text: refused
        $this->assertFalse((new Mimes('jpg'))->check('img', $this->text()));
    }

    public function testMimesAcceptsJpegAlias(): void {
        $this->assertStringContainsString('jpg', (new Mimes('jpeg'))->message('img'));
    }

    public function testMaxSizeInKilobytes(): void {
        $this->assertTrue((new MaxSize(1))->check('doc', $this->text(1024)));
        $this->assertFalse((new MaxSize(1))->check('doc', $this->text(1025)));
    }

    public function testEveryFileOfAListMustPass(): void {
        $rule = new Image();
        $this->assertTrue($rule->check('imgs', [$this->png(), $this->png()]));
        $this->assertFalse($rule->check('imgs', [$this->png(), $this->text()]));
    }

    public function testRulesAreRegisteredInValidator(): void {
        $validator = new Validator(['img' => 'required|file|image|mimes:png|max_size:100'], ['img' => $this->png()]);
        $this->assertTrue($validator->validate());

        $validator = new Validator(['img' => 'required|image'], ['img' => null]);
        $this->assertFalse($validator->validate());
    }
}
