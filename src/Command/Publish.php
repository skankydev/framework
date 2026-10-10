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

/**
 * Publie les ressources par défaut du framework (Publishable/) dans le
 * projet, pour qu'il puisse les personnaliser : vues d'erreur, templates du
 * CrudMaker, parts utilitaires (table, paginator), templates des fields de
 * FormBuilder, traductions du framework.
 *
 * N'écrit jamais dans config/master.config.php (trop risqué de le modifier
 * automatiquement) : affiche seulement le snippet à y vérifier/ajouter.
 */
class Publish extends MasterCommand {

	static protected string $signature = 'publish';
	static protected string $help = 'skankydev.cli.publish.help';

	private const RESOURCES = ['error', 'template', 'part', 'fields', 'lang'];

	/**
	 * @param array $arg accepte `--publish=<ressource>` ou `-p=<ressource>` pour choisir
	 *                   sans passer par le menu interactif (valeurs : error, template, part, fields, lang, all) ;
	 *                   `-h` affiche l'aide (arrive sous la forme `['help' => true]`, cf. ArgParser)
	 */
	public function run(array $arg = []): void {
		if (!empty($arg['help'])) {
			$this->displayHelp();
			return;
		}

		$this->info('═══════════════════════════════════════');
		$this->success('    Publish - SkankyDev');
		$this->info('═══════════════════════════════════════');
		$this->text('');

		$choice = $arg['publish'] ?? $arg['p'] ?? null;
		$choice = $choice ? strtolower(trim($choice)) : $this->promptChoice();

		$all = [...self::RESOURCES, 'all'];
		if (!in_array($choice, $all)) {
			$this->error(__('skankydev.cli.publish.unknown_resource', ['resource' => $choice]));
			$this->text(__('skankydev.cli.publish.available', ['list' => implode(', ', $all)]));
			return;
		}

		$resources = $choice === 'all' ? self::RESOURCES : [$choice];

		foreach ($resources as $resource) {
			$this->line();
			$this->warning(__('skankydev.cli.publish.publishing', ['resource' => $resource]));
			match ($resource) {
				'error'    => $this->publishError(),
				'template' => $this->publishTemplate(),
				'part'     => $this->publishPart(),
				'fields'   => $this->publishFields(),
				'lang'     => $this->publishLang(),
			};
		}

		$this->text('');
		$this->success(__('skankydev.cli.done'));
	}

	/**
	 * Affiche l'aide de la commande (`php craft publish -h`) : la ligne
	 * signature/description au même format que le listing de
	 * `CliApplication::cmdHelp()`, puis le détail de chaque ressource publiable.
	 */
	private function displayHelp(): void {
		$this->text('');
		echo '  ' . vert(str_pad(static::$signature, 20)) . str_pad(static::getInfo()['help'], 60) . "\n";
		$this->text('');
		$this->warning(__('skankydev.cli.publish.resources_title'));
		$this->text('');
		foreach ([...self::RESOURCES, 'all'] as $name) {
			echo '    ' . cyan(str_pad($name, 12)) . __('skankydev.cli.publish.resource.' . $name) . "\n";
		}
		$this->text('');
		$this->text(__('skankydev.cli.publish.no_argument'));
		$this->text('');
	}

	/**
	 * Affiche la liste numérotée des ressources publiables et retourne le
	 * choix de l'utilisateur (même mécanisme que CrudMaker::initFields()).
	 */
	private function promptChoice(): string {
		$options = [...self::RESOURCES, 'all'];
		$answer = $this->choice($options, vert(__('skankydev.cli.publish.prompt')));
		return strtolower(trim($options[$answer] ?? ''));
	}

	private function publishError(): void {
		$this->copyDirectory(Config::get('view.error'), VIEW_FOLDER . DS . 'error');
		$this->copyFile(Config::get('view.error_layout'), VIEW_FOLDER . DS . 'layout' . DS . 'error.php');

		$this->configHint([
			"'view' => [",
			"    'error' => VIEW_FOLDER.DS.'error',",
			"    'error_layout' => VIEW_FOLDER.DS.'layout'.DS.'error.php',",
			"],",
		]);
	}

	private function publishTemplate(): void {
		$this->copyDirectory(Config::get('template.folder'), TEMPLATE_FOLDER);

		$this->configHint([
			"'template' => [",
			"    'folder' => TEMPLATE_FOLDER,",
			"],",
		]);
	}

	private function publishPart(): void {
		// view.folder pointe déjà sur l'app (part() ne passe pas par une clé
		// Publishable dédiée) : rien à ajouter dans master.config.php ici.
		$this->copyDirectory(
			PUBLISHABLE_FOLDER . DS . 'view' . DS . 'part',
			VIEW_FOLDER . DS . 'part'
		);
	}

	private function publishFields(): void {
		$this->copyDirectory(Config::get('view.fields'), VIEW_FOLDER . DS . 'fields');

		$this->configHint([
			"'view' => [",
			"    'fields' => VIEW_FOLDER.DS.'fields',",
			"],",
		]);
	}

	private function publishLang(): void {
		// Translator lit d'abord le skankydev.php du projet (i18n.path) et ne
		// retombe sur Publishable que s'il est absent : rien à ajouter dans la config.
		$this->copyDirectory(
			PUBLISHABLE_FOLDER . DS . 'lang',
			Config::get('i18n.path') ?? LANG_FOLDER
		);
	}

	/**
	 * Copie récursivement le contenu d'un dossier, fichier par fichier,
	 * en demandant confirmation avant d'écraser un fichier existant.
	 */
	private function copyDirectory(string $source, string $destination): void {
		if (!is_dir($source)) {
			$this->error(__('skankydev.cli.publish.source_not_found', ['path' => $source]));
			return;
		}
		if (realpath($source) === realpath($destination)) {
			$this->warning(__('skankydev.cli.publish.already_active', ['path' => $destination]));
			return;
		}
		if (!is_dir($destination)) {
			mkdir($destination, 0775, true);
		}

		foreach (scandir($source) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$from = $source . DS . $entry;
			$to = $destination . DS . $entry;

			if (is_dir($from)) {
				$this->copyDirectory($from, $to);
			} else {
				$this->copyFile($from, $to);
			}
		}
	}

	/**
	 * Copie un fichier, en demandant confirmation avant d'écraser le fichier
	 * de destination s'il existe déjà.
	 */
	private function copyFile(string $from, string $to): void {
		if (!file_exists($from)) {
			$this->error(__('skankydev.cli.publish.source_not_found', ['path' => $from]));
			return;
		}
		if (realpath($from) === realpath($to)) {
			return;
		}
		if (file_exists($to)) {
			$confirm = $this->valide(__('skankydev.cli.publish.exists_overwrite', ['path' => $to]));
			if ($confirm !== 'y') {
				$this->warning(__('skankydev.cli.publish.skipped', ['path' => $to]));
				return;
			}
		}

		$destDir = dirname($to);
		if (!is_dir($destDir)) {
			mkdir($destDir, 0775, true);
		}

		copy($from, $to);
		$this->success(__('skankydev.cli.publish.published', ['path' => $to]));
	}

	/**
	 * Affiche le snippet de config à vérifier/ajouter dans config/master.config.php.
	 * Toujours affiché, même si déjà présent : on ne modifie jamais ce fichier
	 * automatiquement (trop risqué de casser sa structure).
	 */
	private function configHint(array $lines): void {
		$this->text('');
		$this->info(__('skankydev.cli.publish.config_hint'));
		$this->text('');
		foreach ($lines as $line) {
			$this->text('    ' . cyan($line));
		}
		$this->text('');
	}

}
