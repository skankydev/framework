<?php

namespace SkankyTest\TestCase\I18n;

use PHPUnit\Framework\TestCase;
use SkankyDev\I18n\CatalogFile;
use SkankyDev\I18n\Translator;

/**
 * Les traductions livrées par le framework doivent avoir exactement les
 * mêmes clés dans toutes les langues, sans valeur à traduire.
 */
class PublishableLangTest extends TestCase
{
    private function catalogs(): array {
        $catalogs = [];
        foreach (glob(PUBLISHABLE_FOLDER . '/lang/*/' . Translator::FRAMEWORK_DOMAIN . '.php') as $path) {
            $catalogs[basename(dirname($path))] = new CatalogFile($path);
        }
        return $catalogs;
    }

    public function testShipsEnglishAndFrench(): void {
        $this->assertEqualsCanonicalizing(['en', 'fr'], array_keys($this->catalogs()));
    }

    public function testSameKeysInEveryLanguage(): void {
        $catalogs  = $this->catalogs();
        $reference = array_keys($catalogs['en']->leaves());
        foreach ($catalogs as $language => $catalog) {
            $keys = array_keys($catalog->leaves());
            $this->assertEquals([], array_diff($reference, $keys), "Missing in {$language}");
            $this->assertEquals([], array_diff($keys, $reference), "Only in {$language}");
        }
    }

    public function testEverythingIsTranslatedAndValidIcu(): void {
        foreach ($this->catalogs() as $language => $catalog) {
            $this->assertEquals([], $catalog->untranslated(), "Untranslated in {$language}");
            foreach ($catalog->leaves() as $key => $message) {
                $this->assertNotFalse(
                    \MessageFormatter::create($language, $message) ?? false,
                    "Invalid ICU message {$language}:{$key}"
                );
            }
        }
    }
}
