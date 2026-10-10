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

namespace SkankyDev\I18n;

use SkankyDev\Config\Config;
use SkankyDev\Utilities\Log;
use SkankyDev\Utilities\Traits\ArrayPathable;

/**
 * Traduit les clés `domaine.chemin.vers.message` à partir des catalogues PHP
 * `{i18n.path}/{langue}/{domaine}.php`, et les formate en ICU MessageFormat
 * (interpolation `{name}`, pluriels CLDR, select...) via ext/intl.
 *
 * La langue choisit le catalogue, la locale complète sert au formatage :
 * pour `fr_CA`, on cherche dans `fr_CA/` (optionnel), puis `fr/`, puis la
 * langue `i18n.fallback`, et en dernier recours on renvoie la clé brute.
 *
 * Le domaine `skankydev` est réservé au framework : s'il n'est pas publié
 * dans le projet, il est lu dans Publishable/lang.
 */
class Translator {

	use ArrayPathable;

	/** Domaine des messages du framework (validation, upload...). */
	const FRAMEWORK_DOMAIN = 'skankydev';

	private static ?string $locale = null;

	/** Catalogues déjà chargés : [langue][domaine] => array */
	private static array $catalogs = [];

	/** Clés manquantes déjà signalées dans le log (une fois par processus). */
	private static array $reported = [];

	/**
	 * Fixe la locale courante. Accepte les deux notations (`fr_FR` ou `fr-FR`),
	 * stockée au format ICU.
	 */
	static function setLocale(string $locale): void {
		self::$locale = self::canonicalize($locale);
	}

	/** Locale courante, sinon `i18n.locale`. */
	static function getLocale(): string {
		return self::$locale ?? self::canonicalize(Config::get('i18n.locale') ?? 'fr_FR');
	}

	/** Langue de la locale courante : `fr` pour `fr_CA`. */
	static function getLanguage(): string {
		return \Locale::getPrimaryLanguage(self::getLocale());
	}

	/**
	 * Convertit une locale ICU (`fr_CA`) en tag BCP 47 (`fr-CA`), la notation
	 * du web : attribut HTML `lang`, Accept-Language, Intl en JS.
	 */
	static function toLanguageTag(?string $locale = null): string {
		return str_replace('_', '-', $locale ?? self::getLocale());
	}

	/**
	 * Traduit une clé et la formate avec $args.
	 * Retourne toujours une string : la clé brute si elle est introuvable ou
	 * si le message ICU est invalide.
	 * @param  string $key  `domaine.chemin`, ex : `blog.post.count`
	 * @param  array  $args arguments nommés du message, ex : `['count' => 3]`
	 */
	static function get(string $key, array $args = []): string {
		$pattern = self::find($key);
		if ($pattern === null) {
			self::report("Traduction manquante : {$key} (" . self::getLocale() . ')');
			return $key;
		}
		return self::format($key, $pattern, $args);
	}

	/** True si la clé existe dans l'une des langues de la chaîne de repli. */
	static function has(string $key): bool {
		return self::find($key) !== null;
	}

	/**
	 * Vide la locale et les catalogues chargés. Utile dans les tests, et pour
	 * un processus qui dure (worker de queue) quand les fichiers changent.
	 */
	static function reset(): void {
		self::$locale   = null;
		self::$catalogs = [];
		self::$reported = [];
	}

	/**
	 * Langues à essayer, dans l'ordre : locale complète, sa langue, puis le
	 * fallback (et sa langue s'il est donné en locale complète).
	 */
	static function candidates(): array {
		$locale   = self::getLocale();
		$fallback = self::canonicalize(Config::get('i18n.fallback') ?? $locale);
		return array_values(array_unique([
			$locale,
			\Locale::getPrimaryLanguage($locale),
			$fallback,
			\Locale::getPrimaryLanguage($fallback),
		]));
	}

	/** Cherche le message brut de la clé dans la chaîne de repli. */
	private static function find(string $key): ?string {
		if (!str_contains($key, '.')) {
			return null;
		}
		[$domain, $path] = explode('.', $key, 2);

		foreach (self::candidates() as $language) {
			$catalog = self::catalog($language, $domain);
			$message = self::arrayGet($path, $catalog);
			if (is_string($message)) {
				return $message;
			}
		}
		return null;
	}

	/** Charge (une seule fois) le catalogue d'un domaine pour une langue. */
	private static function catalog(string $language, string $domain): array {
		if (!isset(self::$catalogs[$language][$domain])) {
			self::$catalogs[$language][$domain] = self::load($language, $domain);
		}
		return self::$catalogs[$language][$domain];
	}

	/**
	 * Lit `{i18n.path}/{langue}/{domaine}.php`. Pour le domaine du framework,
	 * retombe sur Publishable/lang tant que le projet ne l'a pas publié.
	 * Le fichier entier l'emporte : pas de fusion clé par clé.
	 */
	private static function load(string $language, string $domain): array {
		$file = (Config::get('i18n.path') ?? LANG_FOLDER) . DS . $language . DS . $domain . '.php';
		if (!file_exists($file) && $domain === self::FRAMEWORK_DOMAIN) {
			$file = PUBLISHABLE_FOLDER . DS . 'lang' . DS . $language . DS . $domain . '.php';
		}
		if (!file_exists($file)) {
			return [];
		}
		$catalog = require $file;
		return is_array($catalog) ? $catalog : [];
	}

	/**
	 * Formate le message en ICU MessageFormat avec la locale complète.
	 * Un pattern invalide ne lève pas d'exception : on logue et on rend la clé.
	 */
	private static function format(string $key, string $pattern, array $args): string {
		try {
			$formatter = new \MessageFormatter(self::getLocale(), $pattern);
			$message   = $formatter->format($args);
		} catch (\IntlException|\ValueError $e) {
			$message = false;
		}
		if ($message === false) {
			self::report("Message ICU invalide : {$key} « {$pattern} »");
			return $key;
		}
		return $message;
	}

	/** Signale un problème dans le log i18n, en debug seulement, une fois par message. */
	private static function report(string $message): void {
		if (!Config::get('debug') || isset(self::$reported[$message])) {
			return;
		}
		self::$reported[$message] = true;
		Log::warning($message, 'i18n');
	}

	/** `fr-FR` / `fr_FR` / `fr_FR@currency=EUR` → `fr_FR` */
	private static function canonicalize(string $locale): string {
		$canonical = \Locale::canonicalize($locale) ?? $locale;
		return explode('@', $canonical, 2)[0];
	}
}
