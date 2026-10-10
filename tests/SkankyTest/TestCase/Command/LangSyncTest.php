<?php

namespace SkankyTest\TestCase\Command;

use PHPUnit\Framework\TestCase;
use SkankyDev\Command\LangSync;
use SkankyDev\Config\Config;
use SkankyDev\I18n\Translator;

class LangSyncTest extends TestCase
{
    private string $dir;
    private array $config;

    protected function setUp(): void {
        $this->config = [
            'i18n'        => Config::get('i18n'),
            'view.folder' => Config::get('view.folder'),
            'Module'      => Config::get('Module'),
        ];
        $this->dir = sys_get_temp_dir() . '/skankydev_langsync_' . uniqid();
        mkdir($this->dir . '/view', 0775, true);
        mkdir($this->dir . '/lang/en', 0775, true);

        file_put_contents($this->dir . '/view/page.php', implode("\n", [
            "<h1><?= __('app.title') ?></h1>",
            "<p><?= __('app.post.count', ['count' => 2]) ?></p>",
            "<p><?= __('app.status.' . \$status) ?></p>",
            "<p><?= __('skankydev.validation.required') ?></p>",
        ]));
        file_put_contents($this->dir . '/lang/en/app.php', "<?php\n/** header */\nreturn [\n\t'title' => 'Title',\n\t'old' => 'Old',\n\t'status' => ['draft' => 'Draft'],\n];\n");

        Config::set('Module', []);
        Config::set('view.folder', $this->dir . '/view');
        Config::set('i18n', [
            'locale'    => 'en_US',
            'fallback'  => 'en',
            'available' => ['en_US', 'fr_FR'],
            'path'      => $this->dir . '/lang',
        ]);
        Translator::reset();
    }

    protected function tearDown(): void {
        foreach ($this->config as $key => $value) {
            Config::set($key, $value);
        }
        Translator::reset();
        foreach (['/view/page.php', '/lang/en/app.php', '/lang/fr/app.php'] as $file) {
            @unlink($this->dir . $file);
        }
        @rmdir($this->dir . '/lang/fr');
        @rmdir($this->dir . '/lang/en');
        @rmdir($this->dir . '/lang');
        @rmdir($this->dir . '/view');
        @rmdir($this->dir);
    }

    private function sync(array $arg = []): string {
        ob_start();
        (new LangSync())->run($arg);
        return ob_get_clean();
    }

    private function catalog(string $language): array {
        return require $this->dir . "/lang/{$language}/app.php";
    }

    public function testAddsMissingKeysAsNullInEveryLanguage(): void {
        $output = $this->sync();

        $this->assertEquals([
            'title'  => 'Title',
            'old'    => 'Old',
            'status' => ['draft' => 'Draft'],
            'post'   => ['count' => null],
        ], $this->catalog('en'));
        $this->assertEquals([
            'post'  => ['count' => null],
            'title' => null,
        ], $this->catalog('fr'));

        $this->assertStringContainsString('+ app.post.count', $output);
        $this->assertStringContainsString('app.old (unused)', $output);
        // dynamic prefix: app.status.draft is not reported as unused
        $this->assertStringNotContainsString('app.status.draft', $output);
        $this->assertStringContainsString('app.status.…', $output);
        // framework domain is never touched
        $this->assertFileDoesNotExist($this->dir . '/lang/en/skankydev.php');
    }

    public function testKeepsHeader(): void {
        $this->sync();
        $this->assertStringStartsWith("<?php\n/** header */\nreturn [", file_get_contents($this->dir . '/lang/en/app.php'));
    }

    public function testDryRunWritesNothing(): void {
        $before = file_get_contents($this->dir . '/lang/en/app.php');
        $output = $this->sync(['dry-run' => true]);

        $this->assertEquals($before, file_get_contents($this->dir . '/lang/en/app.php'));
        $this->assertFileDoesNotExist($this->dir . '/lang/fr/app.php');
        $this->assertStringContainsString('+ app.post.count', $output);
        $this->assertStringContainsString('Nothing written', $output);
    }

    public function testPruneRemovesUnusedButKeepsDynamicPrefixes(): void {
        $output = $this->sync(['prune' => true]);

        $catalog = $this->catalog('en');
        $this->assertArrayNotHasKey('old', $catalog);
        $this->assertEquals(['draft' => 'Draft'], $catalog['status']);
        $this->assertStringContainsString('app.old (removed)', $output);
    }

    public function testSecondRunReportsUntranslated(): void {
        $this->sync();
        $output = $this->sync();

        $this->assertStringContainsString('app.post.count (not translated)', $output);
        $this->assertStringNotContainsString('+ app.', $output);
    }

    public function testCheckPassesWhenEverythingIsTranslated(): void {
        file_put_contents($this->dir . '/lang/en/app.php', "<?php return ['title' => 'Title', 'post' => ['count' => '{count} posts']];");
        mkdir($this->dir . '/lang/fr');
        file_put_contents($this->dir . '/lang/fr/app.php', "<?php return ['title' => 'Titre', 'post' => ['count' => '{count} articles']];");

        $output = $this->sync(['check' => true]);
        $this->assertStringContainsString('No missing key or translation', $output);
    }
}
