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

/**
 * Choisit la locale parmi celles que le projet accepte (`i18n.available`) à
 * partir du header Accept-Language.
 *
 * Le framework ne fait que l'outil : c'est l'application qui décide où
 * l'appeler (bootstrap, middleware avec la session...).
 *
 * Pas de Locale::acceptFromHttp() (renvoie la préférence du navigateur sans
 * tenir compte de ce qu'on a) ni de Locale::lookup() (ne fait correspondre
 * `fr` à `fr_FR` que dans un sens, et renvoie la valeur en minuscules).
 */
class LocaleNegotiator {

	/**
	 * Retourne la locale disponible qui correspond le mieux au header, telle
	 * qu'écrite dans la config, sinon $default.
	 * Pour chaque langue demandée (par ordre de préférence) : correspondance
	 * exacte d'abord, puis même langue (`fr_CA` demandé → `fr_FR` disponible).
	 * @param ?string $header    défaut : $_SERVER['HTTP_ACCEPT_LANGUAGE']
	 * @param ?array  $available défaut : `i18n.available`
	 * @param ?string $default   défaut : `i18n.locale`
	 */
	static function negotiate(?string $header = null, ?array $available = null, ?string $default = null): string {
		$header    ??= $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
		$available ??= Config::get('i18n.available') ?? [];
		$default   ??= Config::get('i18n.locale') ?? 'fr_FR';

		foreach (self::parse($header) as $requested) {
			$sameLanguage = null;
			foreach ($available as $locale) {
				$canonical = \Locale::canonicalize($locale) ?? $locale;
				if (strcasecmp($canonical, $requested) === 0) {
					return $locale;
				}
				if ($sameLanguage === null && \Locale::getPrimaryLanguage($canonical) === \Locale::getPrimaryLanguage($requested)) {
					$sameLanguage = $locale;
				}
			}
			if ($sameLanguage !== null) {
				return $sameLanguage;
			}
		}
		return $default;
	}

	/**
	 * Découpe un header Accept-Language en locales ICU triées par préférence
	 * (`q` décroissant, ordre du header à égalité). Ignore `*` et `q=0`.
	 * `fr-CA,fr;q=0.9,en;q=0.8` → ['fr_CA', 'fr', 'en']
	 */
	static function parse(string $header): array {
		$entries = [];
		foreach (explode(',', $header) as $index => $part) {
			$params = array_map('trim', explode(';', $part));
			$tag    = array_shift($params);
			if ($tag === '' || $tag === '*') {
				continue;
			}
			$quality = 1.0;
			foreach ($params as $param) {
				if (str_starts_with($param, 'q=')) {
					$quality = (float) substr($param, 2);
				}
			}
			if ($quality <= 0) {
				continue;
			}
			$entries[] = ['locale' => \Locale::canonicalize($tag) ?? $tag,'q' => $quality, 'index' => $index];
		}
		usort($entries, fn($a, $b) => [$b['q'], $a['index']] <=> [$a['q'], $b['index']]);
		return array_column($entries, 'locale');
	}
}
