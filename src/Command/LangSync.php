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

namespace SkankyDev\Command;

use SkankyDev\Config\Config;
use SkankyDev\I18n\CatalogFile;
use SkankyDev\I18n\KeyExtractor;
use SkankyDev\I18n\Translator;

/**
 * Met les fichiers de langue à jour avec les clés utilisées dans le code.
 *
 * Scanne `src/{Module}/` de chaque module et le dossier de vues, puis pour
 * chaque langue de `i18n.available` (+ `i18n.fallback`) : ajoute les clés
 * manquantes avec la valeur null (pas encore traduite), et signale les clés
 * inutilisées et celles qui restent à traduire.
 *
 * Options :
 * - `--dry-run` : affiche le rapport sans rien écrire ;
 * - `--prune`   : supprime les clés inutilisées (jamais par défaut : une clé
 *                 construite à l'exécution n'est pas visible au scan) ;
 * - `--check`   : n'écrit rien, et sort en erreur (code 1) s'il manque une
 *                 clé ou une traduction. Pratique en CI.
 *
 * Le domaine `skankydev` (celui du framework) n'est jamais touché.
 */
class LangSync extends MasterCommand {

	static protected string $signature = 'lang-sync';
	static protected string $help = 'skankydev.cli.lang_sync.help';

	public function run(array $arg = []): void {
		$check  = !empty($arg['check']);
		$prune  = !empty($arg['prune']) && !$check;
		$write  = empty($arg['dry-run']) && !$check;

		$extractor = new KeyExtractor();
		$scanned   = 0;
		foreach ($this->sourceFolders() as $folder) {
			$scanned += $extractor->extractDirectory($folder);
		}

		$keys = array_filter(
			$extractor->keys(),
			fn($key) => str_contains($key, '.') && !str_starts_with($key, Translator::FRAMEWORK_DOMAIN . '.'),
			ARRAY_FILTER_USE_KEY
		);
		$this->info(__('skankydev.cli.lang_sync.scanned', ['files' => $scanned, 'keys' => count($keys)]));

		$prefixes = $this->reportDynamic($extractor->dynamic());
		$keys     = $this->dropConflicts($keys);

		$domains = [];
		foreach (array_keys($keys) as $key) {
			[$domain, $path] = explode('.', $key, 2);
			$domains[$domain][] = $path;
		}

		$problems = 0;
		$changed  = false;
		foreach ($this->languages() as $language) {
			foreach ($this->domainsFor($language, array_keys($domains)) as $domain) {
				$result    = $this->syncFile($language, $domain, $domains[$domain] ?? [], $prefixes, $prune);
				$problems += $result['problems'];
				if ($result['changed'] && $write) {
					$result['file']->save();
					$this->success(__('skankydev.cli.lang_sync.written', ['file' => $this->relative($result['file']->path)]));
					$changed = true;
				}
			}
		}

		$this->text('');
		if (!$write) {
			$this->warning(__('skankydev.cli.lang_sync.nothing_written'));
		}
		if ($check && $problems > 0) {
			$this->error(__('skankydev.cli.lang_sync.check_failed', ['count' => $problems]));
			exit(1);
		}
		if ($problems === 0 && !$changed) {
			$this->success(__('skankydev.cli.lang_sync.up_to_date'));
		}
	}

	/**
	 * Compare un fichier de langue aux clés du code et affiche ce qui ne va pas.
	 * @param  string[] $paths    chemins (sans le domaine) utilisés dans le code
	 * @param  string[] $prefixes préfixes des clés dynamiques : ce qui commence par l'un d'eux n'est pas « inutilisé »
	 * @return array{file: CatalogFile, changed: bool, problems: int}
	 */
	private function syncFile(string $language, string $domain, array $paths, array $prefixes, bool $prune): array {
		$file    = new CatalogFile($this->langPath() . DS . $language . DS . $domain . '.php');
		$added   = [];
		$changed = false;

		foreach ($paths as $path) {
			if ($file->has($path)) {
				continue;
			}
			if (!$file->set($path, null)) {
				$this->error(__('skankydev.cli.lang_sync.conflict', ['key' => "{$domain}.{$path}"]));
				continue;
			}
			$added[] = $path;
			$changed = true;
		}

		$unused = [];
		foreach (array_keys($file->leaves()) as $path) {
			$key = "{$domain}.{$path}";
			if (in_array($path, $paths, true) || $this->matchesPrefix($key, $prefixes)) {
				continue;
			}
			$unused[] = $path;
			if ($prune) {
				$file->remove($path);
				$changed = true;
			}
		}

		$untranslated = array_diff($file->untranslated(), $added);

		if ($added || $unused || $untranslated) {
			$this->warning($this->relative($file->path));
			foreach ($added as $path) {
				$this->text('  ' . vert('+ ' . "{$domain}.{$path}"));
			}
			foreach ($untranslated as $path) {
				$this->text('  ' . jaune(__('skankydev.cli.lang_sync.untranslated', ['key' => "{$domain}.{$path}"])));
			}
			foreach ($unused as $path) {
				$label = $prune ? 'skankydev.cli.lang_sync.pruned' : 'skankydev.cli.lang_sync.unused';
				$this->text('  ' . grisClair(__($label, ['key' => "{$domain}.{$path}"])));
			}
		}

		return ['file' => $file, 'changed' => $changed, 'problems' => count($added) + count($untranslated)];
	}

	/**
	 * Affiche les appels dont la clé n'est pas analysable, et retourne les
	 * préfixes littéraux connus (`skankydev.cli.publish.resource.` . $name).
	 */
	private function reportDynamic(array $dynamic): array {
		if ($dynamic === []) {
			return [];
		}
		$this->text('');
		$this->warning(__('skankydev.cli.lang_sync.dynamic_title'));
		$prefixes = [];
		foreach ($dynamic as $call) {
			$this->text('  ' . grisClair($this->relative($call['where'])) . ($call['prefix'] !== null ? ' ' . cyan($call['prefix'] . '…') : ''));
			if ($call['prefix'] !== null) {
				$prefixes[] = $call['prefix'];
			}
		}
		$this->text('');
		return array_unique($prefixes);
	}

	/**
	 * Une clé utilisée à la fois comme message et comme groupe (`blog.post`
	 * et `blog.post.title`) ne peut pas exister dans un tableau : on la signale et on l'écarte.
	 */
	private function dropConflicts(array $keys): array {
		foreach (array_keys($keys) as $key) {
			foreach (array_keys($keys) as $other) {
				if (str_starts_with($other, $key . '.')) {
					$this->error(__('skankydev.cli.lang_sync.conflict', ['key' => $key]));
					unset($keys[$key]);
					break;
				}
			}
		}
		return $keys;
	}

	private function matchesPrefix(string $key, array $prefixes): bool {
		foreach ($prefixes as $prefix) {
			if (str_starts_with($key, $prefix)) {
				return true;
			}
		}
		return false;
	}

	/** Les dossiers scannés : le code de chaque module, et les vues. */
	private function sourceFolders(): array {
		$folders = array_map(fn($module) => SRC_FOLDER . DS . $module, Config::getModuleList() ?? []);
		$folders[] = Config::get('view.folder') ?? VIEW_FOLDER;
		return array_unique($folders);
	}

	/**
	 * Les langues à tenir à jour : celles de `i18n.available` et le fallback.
	 * Une langue, pas une locale : `fr_FR` et `fr_CA` partagent `lang/fr/`
	 * (un dossier régional ne contient que des écarts, il n'est pas synchronisé).
	 */
	private function languages(): array {
		$locales = [...(Config::get('i18n.available') ?? []), Config::get('i18n.fallback') ?? Translator::getLocale()];
		return array_values(array_unique(array_map(fn($locale) => \Locale::getPrimaryLanguage($locale), $locales)));
	}

	/** Les domaines utilisés dans le code, plus ceux qui ont déjà un fichier (pour voir leurs clés inutilisées). */
	private function domainsFor(string $language, array $codeDomains): array {
		$existing = array_map(
			fn($file) => basename($file, '.php'),
			glob($this->langPath() . DS . $language . DS . '*.php') ?: []
		);
		$domains = array_unique([...$codeDomains, ...$existing]);
		$domains = array_filter($domains, fn($domain) => $domain !== Translator::FRAMEWORK_DOMAIN);
		sort($domains);
		return $domains;
	}

	private function langPath(): string {
		return Config::get('i18n.path') ?? LANG_FOLDER;
	}

	/** Chemin lisible dans la console : relatif à la racine du projet. */
	private function relative(string $path): string {
		return str_replace(APP_FOLDER . DS, '', $path);
	}
}
