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


/**
 * String manipulation helpers: case conversion, pluralisation, sanitisation.
 */
trait StringFacility {

	/**
	 * Converts camelCase or PascalCase to dash-case (kebab-case).
	 * Example: `infoActionName` → `info-action-name`
	 * @param  string $string     the string to convert
	 * @param  string $delimiters separator character (default `-`)
	 * @return string
	 */
	public function toDash(string $string, string $delimiters = '-'): string {
		$string = lcfirst($string);
		$string = preg_replace('/[A-Z]/',$delimiters."$0",$string);
		$string = strtolower($string);
		return trim($string,' -');
	}

	/**
	 * Converts dash-case to PascalCase.
	 * Example: `info-action-name` → `InfoActionName`
	 * @param  string $string     the string to convert
	 * @param  string $delimiters word separator (default `-`)
	 * @return string
	 */
	public function toCap(string $string, string $delimiters = '-'): string {
		$string = str_replace($delimiters, ' ', $string);
		$string = ucwords($string);
		$string = str_replace(' ', '', $string);
		return trim($string);
	}

	/**
	 * Converts dash-case to camelCase.
	 * Example: `info-action-name` → `infoActionName`
	 * @param  string $string     the string to convert
	 * @param  string $delimiters word separator (default `-`)
	 * @return string
	 */
	public function toCamel(string $string, string $delimiters = '-'): string {
		$string = lcfirst(ucwords($string, $delimiters));
		$string = str_replace($delimiters, '', $string);
		return trim($string);
	}

	/**
	 * Strips accented and non-ASCII characters from a string.
	 * @param  string $string  the string to sanitise
	 * @param  string $charset source encoding (default `utf-8`)
	 * @return string
	 */
	public function cleanString(string $string, string $charset = 'utf-8'): string {
		$string = htmlentities($string, ENT_NOQUOTES, $charset);
		
		$string = preg_replace('/&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);/', '\1', $string);
		$string = preg_replace('/&([A-za-z]{2})(?:lig);/', '\1', $string); 
		$string = preg_replace('/&[^;]+;/', '', $string);
		$string = str_replace(' ', '', $string);
		
		return $string;
	}

	/**
	 * Converts a dot-notation string to a filesystem path using the DS constant.
	 * Example: `module.part.scenario` → `module/part/scenario`
	 */
	public function dotToFolder(string $string): string {
		return implode(DS, explode('.', $string));
	}

	/**
	 * Returns the English plural form of a word.
	 * Handles common suffixes (y → ies, s/x → es, default → s).
	 */
	public function pluralize(string $word): string {
		if (str_ends_with($word, 'y')) {
			return substr($word, 0, -1) . 'ies';
		}
		if (str_ends_with($word, 's') || str_ends_with($word, 'x')) {
			return $word . 'es';
		}
		return $word . 's';
	}

	/**
	 * Returns the English singular form of a word.
	 * Handles a set of irregular forms and common suffix rules.
	 */
	public function singularize(string $word): string {
		$irregulars = [
			'children' => 'child',
			'people' => 'person',
			'men' => 'man',
			'women' => 'woman',
			'teeth' => 'tooth',
			'feet' => 'foot',
			'mice' => 'mouse',
			'geese' => 'goose',
		];
		
		$lower = strtolower($word);
		if (isset($irregulars[$lower])) {
			// Préserver la casse originale
			return $this->preserveCase($word, $irregulars[$lower]);
		}
		
		// Règles de singularisation
		$rules = [
			'/ies$/i' => 'y',
			'/ves$/i' => 'f',
			'/ses$/i' => 's',
			'/xes$/i' => 'x',
			'/ches$/i' => 'ch', 
			'/shes$/i' => 'sh',
			'/s$/i' => '', 
		];
		
		foreach ($rules as $pattern => $replacement) {
			if (preg_match($pattern, $word)) {
				return preg_replace($pattern, $replacement, $word);
			}
		}
		
		return $word;
	}

	/**
	 * Applies the casing of the original word to the replacement.
	 * If $original starts with an uppercase letter, $replacement is ucfirst'd.
	 */
	public function preserveCase(string $original, string $replacement): string {
		if (ctype_upper($original[0])) {
			return ucfirst($replacement);
		}
		return $replacement;
	}

	/**
	 * Converts a slug or snake_case string to a human-readable title.
	 * Example: `my_field-name` → `My Field Name`
	 */
	public function toHuman(string $string): string {
		$string = str_replace(['-', '_'], ' ', $string);
		return ucwords($string);
	}

	/**
	 * 
	 */
	static function slugify(string $text): string {
		if (class_exists(\Transliterator::class)) {
			$text = \Transliterator::create('Any-Latin; Latin-ASCII')?->transliterate($text) ?? $text;
		} else {
			$text = strtr($text, [
				'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','æ'=>'ae','ç'=>'c',
				'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
				'ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','œ'=>'oe',
				'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y','ß'=>'ss',
				'À'=>'a','Â'=>'a','Ä'=>'a','Ç'=>'c','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e',
				'Î'=>'i','Ï'=>'i','Ô'=>'o','Ö'=>'o','Œ'=>'oe','Ù'=>'u','Û'=>'u','Ü'=>'u',
			]);
		}
		$text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
		return trim($text, '-');
	}
}