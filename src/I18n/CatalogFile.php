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

/**
 * Un fichier de langue (`lang/{langue}/{domaine}.php`) qu'on peut lire,
 * modifier par chemin à points, et réécrire.
 *
 * À la réécriture, tout ce qui précède le `return` (le `<?php` et le docblock
 * d'en-tête) est conservé tel quel. Le tableau, lui, est régénéré : les
 * commentaires qu'il contenait sont perdus, l'ordre des clés est gardé.
 */
class CatalogFile {

	public array $data = [];

	private string $header = "<?php\n\n";

	/**
	 * @throws \UnexpectedValueException si le fichier existe mais ne retourne pas un tableau
	 */
	public function __construct(public readonly string $path) {
		if (!file_exists($path)) {
			return;
		}
		$data = require $path;
		if (!is_array($data)) {
			throw new \UnexpectedValueException("Language file must return an array: {$path}");
		}
		$this->data   = $data;
		$this->header = self::headerOf(file_get_contents($path)) ?? $this->header;
	}

	/** True si le chemin existe, même avec une valeur null (clé pas encore traduite). */
	public function has(string $path): bool {
		$pointer = $this->data;
		foreach (explode('.', $path) as $key) {
			if (!is_array($pointer) || !array_key_exists($key, $pointer)) {
				return false;
			}
			$pointer = $pointer[$key];
		}
		return true;
	}

	/**
	 * Ajoute ou remplace une valeur. Retourne false sans rien toucher si le
	 * chemin traverse un message (`post` est un texte, on ne peut pas créer `post.title`).
	 */
	public function set(string $path, mixed $value): bool {
		$keys    = explode('.', $path);
		$last    = array_pop($keys);
		$pointer = &$this->data;
		foreach ($keys as $key) {
			if (!array_key_exists($key, $pointer)) {
				$pointer[$key] = [];
			} elseif (!is_array($pointer[$key])) {
				return false;
			}
			$pointer = &$pointer[$key];
		}
		$pointer[$last] = $value;
		return true;
	}

	/** Supprime une valeur, puis les groupes devenus vides au-dessus d'elle. */
	public function remove(string $path): void {
		self::removeFrom($this->data, explode('.', $path));
	}

	/**
	 * Tous les messages, à plat : ['post.title' => 'Article : {title}', 'post.new' => null, ...]
	 * Un groupe vide n'est pas un message, il n'apparaît pas.
	 */
	public function leaves(): array {
		return self::flatten($this->data);
	}

	/** Les chemins dont la valeur est null : ajoutés par lang-sync, pas encore traduits. */
	public function untranslated(): array {
		return array_keys(array_filter($this->leaves(), 'is_null'));
	}

	/** Écrit le fichier (et son dossier au besoin). */
	public function save(): void {
		$dir = dirname($this->path);
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}
		file_put_contents($this->path, $this->header . 'return ' . self::export($this->data) . ";\n");
	}

	/**
	 * Un tableau en code PHP, au format des fichiers du framework : `[]`,
	 * tabulations, `=>` alignés par niveau, `null` en minuscules.
	 */
	public static function export(array $data, int $depth = 1): string {
		if ($data === []) {
			return '[]';
		}
		$indent = str_repeat("\t", $depth);
		$keys   = array_map(fn($key) => var_export($key, true), array_keys($data));
		$width  = max(array_map('mb_strlen', $keys));

		$lines = [];
		foreach (array_values($data) as $i => $value) {
			$key     = $keys[$i] . str_repeat(' ', $width - mb_strlen($keys[$i]));
			$lines[] = $indent . $key . ' => ' . (is_array($value) ? self::export($value, $depth + 1) : self::scalar($value)) . ',';
		}
		return "[\n" . implode("\n", $lines) . "\n" . str_repeat("\t", $depth - 1) . ']';
	}

	private static function scalar(mixed $value): string {
		return match (true) {
			$value === null => 'null',
			is_bool($value) => $value ? 'true' : 'false',
			default         => var_export($value, true),
		};
	}

	private static function flatten(array $data, string $prefix = ''): array {
		$leaves = [];
		foreach ($data as $key => $value) {
			$path = $prefix . $key;
			if (is_array($value)) {
				$leaves += self::flatten($value, $path . '.');
			} else {
				$leaves[$path] = $value;
			}
		}
		return $leaves;
	}

	private static function removeFrom(array &$data, array $keys): void {
		$key = array_shift($keys);
		if (!array_key_exists($key, $data)) {
			return;
		}
		if ($keys === []) {
			unset($data[$key]);
			return;
		}
		if (is_array($data[$key])) {
			self::removeFrom($data[$key], $keys);
			if ($data[$key] === []) {
				unset($data[$key]);
			}
		}
	}

	/** Le texte avant le premier `return` du fichier (`<?php` + docblock), null s'il n'y en a pas. */
	private static function headerOf(string $source): ?string {
		foreach (\PhpToken::tokenize($source) as $token) {
			if ($token->is(T_RETURN)) {
				return substr($source, 0, $token->pos);
			}
		}
		return null;
	}
}
