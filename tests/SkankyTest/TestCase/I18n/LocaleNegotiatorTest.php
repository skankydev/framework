<?php

namespace SkankyTest\TestCase\I18n;

use PHPUnit\Framework\TestCase;
use SkankyDev\I18n\LocaleNegotiator;

class LocaleNegotiatorTest extends TestCase
{
    private const AVAILABLE = ['fr_FR', 'en_GB'];

    protected function tearDown(): void {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    // ── parse() ──────────────────────────────────────────────────────────────

    public function testParseSortsByQuality(): void {
        $this->assertEquals(['fr_CA', 'fr', 'en'], LocaleNegotiator::parse('en;q=0.8, fr-CA, fr;q=0.9'));
    }

    public function testParseKeepsHeaderOrderOnEqualQuality(): void {
        $this->assertEquals(['de', 'en'], LocaleNegotiator::parse('de, en'));
    }

    public function testParseIgnoresWildcardAndZeroQuality(): void {
        $this->assertEquals(['fr'], LocaleNegotiator::parse('*, en;q=0, fr;q=0.5'));
    }

    public function testParseEmptyHeader(): void {
        $this->assertEquals([], LocaleNegotiator::parse(''));
    }

    // ── negotiate() ──────────────────────────────────────────────────────────

    public function testExactMatch(): void {
        $this->assertEquals('en_GB', LocaleNegotiator::negotiate('en-GB,en;q=0.9', self::AVAILABLE, 'fr_FR'));
    }

    public function testMatchIsCaseInsensitiveAndKeepsConfigSpelling(): void {
        $this->assertEquals('en_GB', LocaleNegotiator::negotiate('en-gb', self::AVAILABLE, 'fr_FR'));
    }

    public function testLanguageOnlyMatchesRegion(): void {
        // Locale::lookup() ne sait pas faire ce cas-là
        $this->assertEquals('fr_FR', LocaleNegotiator::negotiate('fr', self::AVAILABLE, 'en_GB'));
    }

    public function testOtherRegionMatchesSameLanguage(): void {
        $this->assertEquals('fr_FR', LocaleNegotiator::negotiate('fr-CA', self::AVAILABLE, 'en_GB'));
    }

    public function testPreferenceOrderWins(): void {
        $this->assertEquals('en_GB', LocaleNegotiator::negotiate('de-DE, en;q=0.8, fr;q=0.5', self::AVAILABLE, 'fr_FR'));
    }

    public function testExactMatchPreferredOverSameLanguage(): void {
        $this->assertEquals('fr_CA', LocaleNegotiator::negotiate('fr-CA', ['fr_FR', 'fr_CA'], 'en_GB'));
    }

    public function testNoMatchReturnsDefault(): void {
        $this->assertEquals('fr_FR', LocaleNegotiator::negotiate('de-DE, ja', self::AVAILABLE, 'fr_FR'));
    }

    public function testEmptyHeaderReturnsDefault(): void {
        $this->assertEquals('fr_FR', LocaleNegotiator::negotiate('', self::AVAILABLE, 'fr_FR'));
    }

    public function testReadsServerHeaderAndConfigByDefault(): void {
        // config des tests : available = ['fr_FR'], locale = fr_FR
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-BE,en;q=0.5';
        $this->assertEquals('fr_FR', LocaleNegotiator::negotiate());
    }
}
