<?php

namespace SkankyTest\TestCase\I18n;

use PHPUnit\Framework\TestCase;
use SkankyDev\Config\Config;
use SkankyDev\Http\UploadedFile;
use SkankyDev\I18n\Translator;
use SkankyDev\Validation\Rules\MinLength;
use SkankyDev\Validation\Rules\Required;

class TranslatorTest extends TestCase
{
    private array $i18n;

    protected function setUp(): void {
        $this->i18n = Config::get('i18n');
        Translator::reset();
    }

    protected function tearDown(): void {
        Config::set('i18n', $this->i18n);
        Translator::reset();
    }

    // ── Locale ───────────────────────────────────────────────────────────────

    public function testDefaultLocaleComesFromConfig(): void {
        $this->assertEquals('fr_FR', Translator::getLocale());
        $this->assertEquals('fr', Translator::getLanguage());
    }

    public function testSetLocaleAcceptsBothNotations(): void {
        Translator::setLocale('fr-CA');
        $this->assertEquals('fr_CA', Translator::getLocale());
        Translator::setLocale('en_GB');
        $this->assertEquals('en_GB', Translator::getLocale());
    }

    public function testSetLocaleDropsIcuKeywords(): void {
        Translator::setLocale('fr_FR@currency=EUR');
        $this->assertEquals('fr_FR', Translator::getLocale());
    }

    public function testToLanguageTag(): void {
        Translator::setLocale('fr_CA');
        $this->assertEquals('fr-CA', Translator::toLanguageTag());
        $this->assertEquals('zh-Hant-TW', Translator::toLanguageTag('zh_Hant_TW'));
    }

    public function testCandidatesChain(): void {
        Translator::setLocale('fr_CA');
        $this->assertEquals(['fr_CA', 'fr'], Translator::candidates());

        Translator::setLocale('en_GB');
        $this->assertEquals(['en_GB', 'en', 'fr'], Translator::candidates());
    }

    // ── Messages ─────────────────────────────────────────────────────────────

    public function testSimpleLabel(): void {
        $this->assertEquals('Titre', Translator::get('blog.label'));
    }

    public function testInterpolation(): void {
        $this->assertEquals('Article : Mon billet', Translator::get('blog.post.title', ['title' => 'Mon billet']));
    }

    public function testArgumentsAreNotParsedAsIcu(): void {
        $this->assertEquals('Article : {x} l\'a', Translator::get('blog.post.title', ['title' => "{x} l'a"]));
    }

    public function testTypographicApostrophe(): void {
        $this->assertEquals('L’article de Simon', Translator::get('blog.post.quote', ['name' => 'Simon']));
    }

    public function testFrenchPluralZeroIsOne(): void {
        $this->assertEquals('0 article', Translator::get('blog.post.nb', ['count' => 0]));
        $this->assertEquals('1 article', Translator::get('blog.post.nb', ['count' => 1]));
        $this->assertEquals('2 articles', Translator::get('blog.post.nb', ['count' => 2]));
    }

    public function testExplicitZeroCase(): void {
        $this->assertEquals('Aucun article', Translator::get('blog.post.count', ['count' => 0]));
        $this->assertEquals('3 articles', Translator::get('blog.post.count', ['count' => 3]));
    }

    public function testEnglishPluralZeroIsOther(): void {
        Translator::setLocale('en_GB');
        $this->assertEquals('0 posts', Translator::get('blog.post.nb', ['count' => 0]));
        $this->assertEquals('1 post', Translator::get('blog.post.nb', ['count' => 1]));
    }

    public function testPluralNumberFollowsLocale(): void {
        Translator::setLocale('en_GB');
        $this->assertEquals('1,234 posts', Translator::get('blog.post.nb', ['count' => 1234]));
    }

    public function testSelect(): void {
        $this->assertEquals('Écrite par Alice', Translator::get('blog.post.by', ['gender' => 'female', 'name' => 'Alice']));
        $this->assertEquals('Écrit par Bob', Translator::get('blog.post.by', ['gender' => 'male', 'name' => 'Bob']));
    }

    public function testHelperFunction(): void {
        $this->assertEquals('Article : X', __('blog.post.title', ['title' => 'X']));
    }

    // ── Fallback chain ───────────────────────────────────────────────────────

    public function testRegionFileOverridesLanguageFile(): void {
        Translator::setLocale('fr_CA');
        $this->assertEquals('Billet : X', Translator::get('blog.post.title', ['title' => 'X']));
    }

    public function testRegionFallsBackToLanguageFile(): void {
        Translator::setLocale('fr_CA');
        $this->assertEquals('Titre', Translator::get('blog.label'));
        $this->assertEquals('Seulement en français', Translator::get('blog.post.only_fr'));
    }

    public function testRegionWithoutFolderUsesLanguage(): void {
        Translator::setLocale('fr_BE');
        $this->assertEquals('Article : X', Translator::get('blog.post.title', ['title' => 'X']));
    }

    public function testMissingKeyFallsBackToFallbackLanguage(): void {
        Translator::setLocale('en_GB');
        $this->assertEquals('Title', Translator::get('blog.label'));
        $this->assertEquals('Seulement en français', Translator::get('blog.post.only_fr'));
    }

    public function testFallbackMessageIsFormattedWithCurrentLocale(): void {
        // message français, mais nombre formaté en en_GB
        Translator::setLocale('en_GB');
        $this->assertEquals('1,234 articles', Translator::get('blog.post.count', ['count' => 1234]));
    }

    // ── Always a string ──────────────────────────────────────────────────────

    public function testMissingKeyReturnsKey(): void {
        $this->assertEquals('blog.post.nope', Translator::get('blog.post.nope'));
        $this->assertFalse(Translator::has('blog.post.nope'));
        $this->assertTrue(Translator::has('blog.label'));
    }

    public function testUnknownDomainReturnsKey(): void {
        $this->assertEquals('nope.key', Translator::get('nope.key'));
    }

    public function testKeyWithoutDomainReturnsKey(): void {
        $this->assertEquals('blog', Translator::get('blog'));
    }

    public function testKeyPointingToArrayReturnsKey(): void {
        $this->assertEquals('blog.post', Translator::get('blog.post'));
    }

    public function testInvalidPatternReturnsKey(): void {
        $this->assertEquals('blog.post.broken', Translator::get('blog.post.broken'));
    }

    // ── Framework domain ─────────────────────────────────────────────────────

    public function testFrameworkDomainFallsBackToPublishable(): void {
        $this->assertEquals('Le champ email est requis', Translator::get('skankydev.validation.required', ['field' => 'email']));
    }

    public function testFrameworkDomainPublishedInProjectWins(): void {
        Config::set('i18n.path', dirname(__DIR__, 3) . '/fixtures/lang_override');
        $this->assertEquals('Obligatoire : email', Translator::get('skankydev.validation.required', ['field' => 'email']));
        // le fichier publié remplace celui du framework en entier : pas de fusion
        $this->assertEquals('skankydev.validation.email', Translator::get('skankydev.validation.email'));
    }

    public function testValidationMessagesFollowLocale(): void {
        $this->assertEquals('Le champ name est requis', (new Required())->message('name'));

        Translator::setLocale('en_GB');
        $this->assertEquals('The name field is required', (new Required())->message('name'));
    }

    public function testValidationMessagePlural(): void {
        $this->assertEquals('Le champ name doit contenir au moins 1 caractère', (new MinLength(1))->message('name'));
        $this->assertEquals('Le champ name doit contenir au moins 3 caractères', (new MinLength(3))->message('name'));
    }

    public function testUploadErrorMessageFollowsLocale(): void {
        $file = new UploadedFile('a.png', '', UPLOAD_ERR_NO_FILE);
        $this->assertEquals('Aucun fichier envoyé', $file->errorMessage());

        Translator::setLocale('en_GB');
        $this->assertEquals('No file was uploaded', $file->errorMessage());
    }
}
