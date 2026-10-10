<?php

namespace SkankyTest\TestCase\I18n;

use PHPUnit\Framework\TestCase;
use SkankyDev\I18n\KeyExtractor;

class KeyExtractorTest extends TestCase
{
    private function extract(string $code): KeyExtractor {
        $extractor = new KeyExtractor();
        $extractor->extractCode($code, 'file.php');
        return $extractor;
    }

    public function testLiteralKeys(): void {
        $extractor = $this->extract("<?php\n__('blog.title');\n\$x = __(\"blog.post.count\", ['count' => 3]);");
        $this->assertEquals([
            'blog.post.count' => ['file.php:3'],
            'blog.title'      => ['file.php:2'],
        ], $extractor->keys());
        $this->assertEquals([], $extractor->dynamic());
    }

    public function testKeyUsedTwiceKeepsBothLocations(): void {
        $extractor = $this->extract("<?php\n__('a.b');\n__('a.b');");
        $this->assertEquals(['a.b' => ['file.php:2', 'file.php:3']], $extractor->keys());
    }

    public function testTemplateWithInlineHtml(): void {
        $extractor = $this->extract("<h1><?= __('app.title') ?></h1>\n<p><?= e(__('app.intro', ['name' => \$n])) ?></p>");
        $this->assertEquals(['app.intro', 'app.title'], array_keys($extractor->keys()));
    }

    public function testFullyQualifiedCall(): void {
        $this->assertArrayHasKey('app.title', $this->extract("<?php \\__('app.title');")->keys());
    }

    public function testEscapedQuotes(): void {
        $extractor = $this->extract("<?php __('app.it\\'s'); __(\"app.say\\\"hi\\\"\");");
        $this->assertEquals(['app.it\'s', 'app.say"hi"'], array_keys($extractor->keys()));
    }

    public function testIgnoresCommentsStringsAndMethods(): void {
        $code = "<?php\n// __('no.comment')\n/* __('no.block') */\n\$s = \"__('no.string')\";\n\$o->__('no.method');\nFoo::__('no.static');\nfunction __(\$key) {}";
        $extractor = $this->extract($code);
        $this->assertEquals([], $extractor->keys());
        $this->assertEquals([], $extractor->dynamic());
    }

    public function testDynamicKeyWithPrefix(): void {
        $extractor = $this->extract("<?php\n__('cli.resource.' . \$name);");
        $this->assertEquals([], $extractor->keys());
        $this->assertEquals([['prefix' => 'cli.resource.', 'where' => 'file.php:2']], $extractor->dynamic());
    }

    public function testDynamicKeyWithoutPrefix(): void {
        $extractor = $this->extract("<?php\n__(\$key);\n__(\"app.{\$x}\");");
        $this->assertEquals([], $extractor->keys());
        $this->assertCount(2, $extractor->dynamic());
        $this->assertNull($extractor->dynamic()[0]['prefix']);
    }

    public function testExtractDirectory(): void {
        $dir = sys_get_temp_dir() . '/skankydev_extract_' . uniqid();
        mkdir($dir . '/sub', 0775, true);
        file_put_contents($dir . '/a.php', "<?php __('app.a');");
        file_put_contents($dir . '/sub/b.php', "<?= __('app.b') ?>");
        file_put_contents($dir . '/c.txt', "__('app.c')");

        $extractor = new KeyExtractor();
        $this->assertEquals(2, $extractor->extractDirectory($dir));
        $this->assertEquals(['app.a', 'app.b'], array_keys($extractor->keys()));
        $this->assertEquals(0, $extractor->extractDirectory($dir . '/absent'));

        unlink($dir . '/sub/b.php');
        unlink($dir . '/a.php');
        unlink($dir . '/c.txt');
        rmdir($dir . '/sub');
        rmdir($dir);
    }
}
