<?php
/**
 * Copyright (c) 2025 SCHENCK Simon
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 * @copyright     Copyright (c) SCHENCK Simon
 *
 */

namespace SkankyDev\Utilities\Traits;

use MongoDB\BSON\UTCDateTime;
use SkankyDev\I18n\Translator;

/**
 * Helpers de vue qui dépendent de la locale courante (cf. Translator) :
 * attribut `lang`, nombres, devises et dates formatés via ext/intl.
 * La traduction elle-même passe par la fonction globale __().
 */
trait TranslatorHelper {

	/** Locale courante au format BCP 47, pour `<html lang="...">` : `fr-FR`. */
	public function htmlLang(): string {
		return Translator::toLanguageTag();
	}

	/**
	 * Nombre formaté selon la locale : `1234.5` → `1 234,5` en fr.
	 * @param ?int $decimals nombre de décimales fixe, null pour laisser ICU décider
	 */
	public function number(int|float $value, ?int $decimals = null): string {
		$formatter = new \NumberFormatter(Translator::getLocale(), \NumberFormatter::DECIMAL);
		if ($decimals !== null) {
			$formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);
		}
		return (string) $formatter->format($value);
	}

	/**
	 * Montant formaté selon la locale : `12.5` → `12,50 €` en fr.
	 * @param string $currency code ISO 4217 (EUR, USD...)
	 */
	public function currency(int|float $amount, string $currency = 'EUR'): string {
		$formatter = new \NumberFormatter(Translator::getLocale(), \NumberFormatter::CURRENCY);
		return (string) $formatter->formatCurrency($amount, $currency);
	}

	/**
	 * Date formatée selon la locale et la timezone par défaut.
	 * `$this->date($post->created)` → `12 oct. 2026` en fr.
	 * @param \DateTimeInterface|UTCDateTime|int|string $date timestamp, chaîne lisible par DateTime, ou date Mongo
	 * @param int     $dateType constante IntlDateFormatter (NONE, SHORT, MEDIUM, LONG, FULL)
	 * @param int     $timeType idem pour l'heure
	 * @param ?string $pattern  pattern ICU (`d MMMM y`), prioritaire sur les types
	 */
	public function date(
		\DateTimeInterface|UTCDateTime|int|string $date,
		int $dateType = \IntlDateFormatter::MEDIUM,
		int $timeType = \IntlDateFormatter::NONE,
		?string $pattern = null
	): string {
		if ($date instanceof UTCDateTime) {
			$date = $date->toDateTime();
		} elseif (is_string($date)) {
			$date = new \DateTimeImmutable($date);
		}
		$formatter = new \IntlDateFormatter(
			Translator::getLocale(),
			$dateType,
			$timeType,
			date_default_timezone_get(),
			null,
			$pattern
		);
		return (string) $formatter->format($date);
	}
}
