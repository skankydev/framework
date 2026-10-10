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
 * Retrouve les clés de traduction utilisées dans du code PHP : les appels
 * `__('domaine.cle')` avec une chaîne littérale.
 *
 * Passe par le tokenizer de PHP (PhpToken) et pas par une regex : les
 * commentaires, les chaînes qui contiennent `__(` ou les méthodes `->__()`
 * ne sont pas pris pour des appels.
 *
 * Une clé construite à l'exécution (`__('cli.resource.' . $name)`,
 * `__($key)`) ne peut pas être devinée : elle est rangée dans dynamic(),
 * avec son préfixe littéral quand il y en a un.
 */
class KeyExtractor {

	/** Clés littérales trouvées : [clé => ['fichier:ligne', ...]] */
	private array $keys = [];

	/** Appels non analysables : [['prefix' => ?string, 'where' => 'fichier:ligne'], ...] */
	private array $dynamic = [];

	/**
	 * Analyse tous les fichiers .php d'un dossier, récursivement.
	 * @return int le nombre de fichiers analysés (0 si le dossier n'existe pas)
	 */
	public function extractDirectory(string $dir): int {
		if (!is_dir($dir)) {
			return 0;
		}
		$count = 0;
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
		foreach ($files as $file) {
			if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
				$this->extractCode(file_get_contents($file->getPathname()), $file->getPathname());
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Analyse un bout de code PHP (un fichier complet, template compris).
	 * @param string $file nom affiché dans les emplacements (`fichier:ligne`)
	 */
	public function extractCode(string $code, string $file = ''): void {
		$tokens = array_values(array_filter(
			\PhpToken::tokenize($code),
			fn(\PhpToken $token) => !$token->isIgnorable()
		));

		foreach ($tokens as $i => $token) {
			if (!$token->is([T_STRING, T_NAME_FULLY_QUALIFIED]) || ltrim($token->text, '\\') !== '__') {
				continue;
			}
			// une méthode ou une déclaration qui s'appelle __, pas un appel du helper
			$previous = $tokens[$i - 1] ?? null;
			if ($previous?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION])) {
				continue;
			}
			if (($tokens[$i + 1] ?? null)?->text !== '(') {
				continue;
			}

			$where    = $file . ':' . $token->line;
			$argument = $tokens[$i + 2] ?? null;
			$next     = ($tokens[$i + 3] ?? null)?->text;

			if ($argument?->is(T_CONSTANT_ENCAPSED_STRING)) {
				$value = self::unquote($argument->text);
				if ($next === ',' || $next === ')') {
					$this->keys[$value][] = $where;
					continue;
				}
				$this->dynamic[] = ['prefix' => $next === '.' ? $value : null, 'where' => $where];
				continue;
			}
			$this->dynamic[] = ['prefix' => null, 'where' => $where];
		}
	}

	/** Clés littérales trouvées : [clé => ['fichier:ligne', ...]], triées. */
	public function keys(): array {
		ksort($this->keys);
		return $this->keys;
	}

	/** Appels dont la clé est construite à l'exécution. */
	public function dynamic(): array {
		return $this->dynamic;
	}

	/** `'l\'article'` → `l'article` (le token garde ses guillemets et ses échappements). */
	private static function unquote(string $literal): string {
		$inner = substr($literal, 1, -1);
		if ($literal[0] === "'") {
			return str_replace(["\\\\", "\\'"], ["\\", "'"], $inner);
		}
		return stripcslashes($inner);
	}
}
