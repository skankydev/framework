<?php

namespace SkankyTest\TestCase\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\UploadedFile;
use SkankyDev\Model\Document\StoredFile;

class UploadedFileTest extends TestCase
{
    private array $tmpFiles = [];
    private string $subdir;
    private string $uploadDir;

    protected function setUp(): void {
        $this->subdir    = 'test_' . bin2hex(random_bytes(4));
        $this->uploadDir = \SkankyDev\Config\Config::get('upload.folder') . DS . $this->subdir;
    }

    protected function tearDown(): void {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
        if (is_dir($this->uploadDir)) {
            array_map('unlink', glob($this->uploadDir . DS . '*'));
            rmdir($this->uploadDir);
        }
    }

    /** Real 3x2 PNG written to a temp file. */
    private function pngFile(): string {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        $img = imagecreatetruecolor(3, 2);
        imagepng($img, $path);
        $this->tmpFiles[] = $path;
        return $path;
    }

    private function textFile(string $content = "<?php echo 'pwned';"): string {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);
        $this->tmpFiles[] = $path;
        return $path;
    }

    private function make(string $clientName, string $tmp, int $error = UPLOAD_ERR_OK): UploadedFile {
        return new UploadedFile($clientName, $tmp, $error, is_file($tmp) ? filesize($tmp) : 0, test: true);
    }

    // ── state & errors ────────────────────────────────────────────────────────

    public function testFromArrayReadsPhpStructure(): void {
        $tmp  = $this->pngFile();
        $file = UploadedFile::fromArray(['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => 42], true);
        $this->assertEquals('a.png', $file->clientName());
        $this->assertEquals(42, $file->size());
        $this->assertTrue($file->isValid());
    }

    public function testNoFileIsNotUploaded(): void {
        $file = $this->make('', '', UPLOAD_ERR_NO_FILE);
        $this->assertFalse($file->isUploaded());
        $this->assertFalse($file->isValid());
    }

    public function testErrorMessageExplainsPhpError(): void {
        $file = $this->make('big.png', '', UPLOAD_ERR_INI_SIZE);
        $this->assertFalse($file->isValid());
        $this->assertStringContainsString('taille maximale', $file->errorMessage());
    }

    public function testValidFileHasNoErrorMessage(): void {
        $this->assertNull($this->make('a.png', $this->pngFile())->errorMessage());
    }

    public function testWithoutTestModeAPlainFileIsNotAValidUpload(): void {
        // is_uploaded_file() refuses a file that did not come from an HTTP upload
        $file = new UploadedFile('a.png', $this->pngFile());
        $this->assertFalse($file->isValid());
    }

    // ── content detection ─────────────────────────────────────────────────────

    public function testMimeAndExtensionComeFromContent(): void {
        $file = $this->make('photo.txt', $this->pngFile());
        $this->assertEquals('image/png', $file->mimeType());
        $this->assertEquals('png', $file->extension());
        $this->assertEquals('txt', $file->clientExtension());
    }

    public function testPhpFileDisguisedAsImageIsNotStoredAsPhp(): void {
        $file = $this->make('photo.php', $this->textFile());
        $this->assertNotEquals('php', $file->extension());
        $this->assertFalse($file->isImage());
    }

    public function testImageDimensions(): void {
        $file = $this->make('a.png', $this->pngFile());
        $this->assertTrue($file->isImage());
        $this->assertEquals([3, 2], $file->dimensions());
    }

    public function testNonImageHasNoDimensions(): void {
        $this->assertNull($this->make('a.txt', $this->textFile('hello'))->dimensions());
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function testStoreMovesFileAndReturnsStoredFile(): void {
        $tmp    = $this->pngFile();
        $stored = $this->make('Ma Photo.png', $tmp)->store($this->subdir);

        $this->assertInstanceOf(StoredFile::class, $stored);
        $this->assertFileDoesNotExist($tmp);
        $this->assertFileExists($stored->fullPath());
        $this->assertStringStartsWith($this->subdir . '/', $stored->path);
        $this->assertEquals('/upload/' . $stored->path, $stored->url());
        $this->assertStringEndsWith('.png', $stored->path);
        $this->assertEquals('Ma Photo.png', $stored->original_name);
        $this->assertEquals('image/png', $stored->mime);
        $this->assertEquals(3, $stored->width);
        $this->assertEquals(2, $stored->height);
    }

    public function testStoreUsesSafeExtensionNotClientOne(): void {
        $stored = $this->make('shell.php', $this->textFile())->store($this->subdir);
        $this->assertStringEndsNotWith('.php', $stored->path);
    }

    public function testStoreWithCustomName(): void {
        $stored = $this->make('a.png', $this->pngFile())->store($this->subdir, 'avatar');
        $this->assertStringEndsWith('/avatar.png', $stored->path);
    }

    public function testStoreRefusesInvalidUpload(): void {
        $this->expectException(\RuntimeException::class);
        $this->make('a.png', '', UPLOAD_ERR_PARTIAL)->store($this->subdir);
    }

    public function testStoreRefusesParentFolder(): void {
        $this->expectException(\RuntimeException::class);
        $this->make('a.png', $this->pngFile())->store('img/../../outside');
    }

    public function testStoreRefusesNameWithPath(): void {
        $this->expectException(\RuntimeException::class);
        $this->make('a.png', $this->pngFile())->store($this->subdir, '../evil');
    }

    public function testStoreFollowsUploadFolderConfig(): void {
        $conf = \SkankyDev\Config\Config::get('upload');
        $folder = sys_get_temp_dir() . DS . 'skanky_upload_' . bin2hex(random_bytes(4));
        \SkankyDev\Config\Config::set('upload', ['folder' => $folder, 'url' => 'https://cdn.test/files']);
        try {
            $stored = $this->make('a.png', $this->pngFile())->store('img');
            $this->assertFileExists($folder . DS . 'img' . DS . basename($stored->path));
            $this->assertStringStartsWith('https://cdn.test/files/img/', $stored->url(true));
            $stored->delete();
            rmdir($folder . DS . 'img');
            rmdir($folder);
        } finally {
            \SkankyDev\Config\Config::set('upload', $conf);
        }
    }
}
