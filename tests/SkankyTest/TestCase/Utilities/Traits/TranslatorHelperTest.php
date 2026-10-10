<?php

namespace SkankyTest\TestCase\Utilities\Traits;

use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use SkankyDev\I18n\Translator;
use SkankyDev\View\HtmlView;

class TranslatorHelperTest extends TestCase
{
    private HtmlView $view;
    private string $timezone;

    protected function setUp(): void {
        Translator::reset();
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $this->view = new HtmlView();
    }

    protected function tearDown(): void {
        date_default_timezone_set($this->timezone);
        Translator::reset();
    }

    /** ICU sépare les milliers / la devise par des espaces insécables : on les normalise. */
    private function spaces(string $value): string {
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', $value);
    }

    public function testHtmlLang(): void {
        $this->assertEquals('fr-FR', $this->view->htmlLang());
        Translator::setLocale('en_GB');
        $this->assertEquals('en-GB', $this->view->htmlLang());
    }

    public function testNumber(): void {
        $this->assertEquals('1 234,5', $this->spaces($this->view->number(1234.5)));
        Translator::setLocale('en_GB');
        $this->assertEquals('1,234.5', $this->view->number(1234.5));
    }

    public function testNumberWithFixedDecimals(): void {
        $this->assertEquals('3,10', $this->view->number(3.1, 2));
    }

    public function testCurrency(): void {
        $this->assertEquals('12,50 €', $this->spaces($this->view->currency(12.5)));
        Translator::setLocale('en_GB');
        $this->assertEquals('£12.50', $this->view->currency(12.5, 'GBP'));
    }

    public function testDateWithPattern(): void {
        $date = new \DateTimeImmutable('2026-10-12 10:00:00');
        $this->assertEquals('12 octobre 2026', $this->view->date($date, pattern: 'd MMMM y'));
        Translator::setLocale('en_GB');
        $this->assertEquals('12 October 2026', $this->view->date($date, pattern: 'd MMMM y'));
    }

    public function testDateAcceptsTimestampStringAndMongoDate(): void {
        $timestamp = (new \DateTimeImmutable('2026-10-12 10:00:00'))->getTimestamp();
        $this->assertEquals('12/10/2026', $this->view->date($timestamp, pattern: 'dd/MM/y'));
        $this->assertEquals('12/10/2026', $this->view->date('2026-10-12', pattern: 'dd/MM/y'));
        $this->assertEquals('12/10/2026', $this->view->date(new UTCDateTime($timestamp * 1000), pattern: 'dd/MM/y'));
    }

    public function testDateDefaultStyleIsLocalized(): void {
        $this->assertStringContainsString('oct.', $this->view->date('2026-10-12'));
    }
}
