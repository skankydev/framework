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
 * FormBuilder.
 *
 * N'écrit jamais dans config/master.config.php (trop risqué de le modifier
 * automatiquement) : affiche seulement le snippet à y vérifier/ajouter.
 */
class Publish extends MasterCommand {

	static protected string $signature = 'publish';
	static protected string $help = 'Publie les ressources par défaut du framework (error, template, part, fields, all) dans le projet';

	private const RESOURCES = ['error', 'template', 'part', 'fields'];

	private const RESOURCE_HELP = [
		'error'    => "Vues d'erreur (debug + production) de l'ExceptionHandler",
		'template' => 'Templates du CrudMaker (Document, Collection, Controller, Form, vues)',
		'part'     => 'Parts utilitaires réutilisables (paginator, table)',
		'fields'   => "Templates des champs de FormBuilder (hors fields spécifiques à l'app, ex: icon, editorjs)",
		'all'      => 'Publie les ressources ci-dessus d\'un coup',
	];

	/**
	 * @param array $arg accepte `--publish=<ressource>` ou `-p=<ressource>` pour choisir
	 *                   sans passer par le menu interactif (valeurs : error, template, part, all) ;
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
			$this->error("Ressource inconnue : {$choice}");
			$this->text('Disponibles : ' . implode(', ', $all));
			return;
		}

		$resources = $choice === 'all' ? self::RESOURCES : [$choice];

		foreach ($resources as $resource) {
			$this->line();
			$this->warning('Publication : ' . $resource);
			match ($resource) {
				'error'    => $this->publishError(),
				'template' => $this->publishTemplate(),
				'part'     => $this->publishPart(),
				'fields'   => $this->publishFields(),
			};
		}

		$this->text('');
		$this->success('✓ Terminé !');
	}

	/**
	 * Affiche l'aide de la commande (`php craft publish -h`) : la ligne
	 * signature/description au même format que le listing de
	 * `CliApplication::cmdHelp()`, puis le détail de chaque ressource publiable.
	 */
	private function displayHelp(): void {
		$this->text('');
		echo '  ' . vert(str_pad(static::$signature, 20)) . str_pad(static::$help, 60) . "\n";
		$this->text('');
		$this->warning('Ressources disponibles (-p=<ressource> ou --publish=<ressource>) :');
		$this->text('');
		foreach (self::RESOURCE_HELP as $name => $description) {
			echo '    ' . cyan(str_pad($name, 12)) . $description . "\n";
		}
		$this->text('');
		$this->text('Sans argument : menu interactif pour choisir la ressource.');
		$this->text('');
	}

	/**
	 * Affiche la liste numérotée des ressources publiables et retourne le
	 * choix de l'utilisateur (même mécanisme que CrudMaker::initFields()).
	 */
	private function promptChoice(): string {
		$options = [...self::RESOURCES, 'all'];
		$answer = $this->choice($options, vert('Que veux-tu publier'));
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

	/**
	 * Copie récursivement le contenu d'un dossier, fichier par fichier,
	 * en demandant confirmation avant d'écraser un fichier existant.
	 */
	private function copyDirectory(string $source, string $destination): void {
		if (!is_dir($source)) {
			$this->error("Source introuvable : {$source}");
			return;
		}
		if (realpath($source) === realpath($destination)) {
			$this->warning("→ {$destination} est déjà la source active, rien à publier");
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
			$this->error("Source introuvable : {$from}");
			return;
		}
		if (realpath($from) === realpath($to)) {
			return;
		}
		if (file_exists($to)) {
			$confirm = $this->valide('⚠ ' . $to . ' existe déjà, écraser');
			if ($confirm !== 'y') {
				$this->warning('→ ' . $to . ' ignoré');
				return;
			}
		}

		$destDir = dirname($to);
		if (!is_dir($destDir)) {
			mkdir($destDir, 0775, true);
		}

		copy($from, $to);
		$this->success('✔️ ' . $to . ' publié');
	}

	/**
	 * Affiche le snippet de config à vérifier/ajouter dans config/master.config.php.
	 * Toujours affiché, même si déjà présent : on ne modifie jamais ce fichier
	 * automatiquement (trop risqué de casser sa structure).
	 */
	private function configHint(array $lines): void {
		$this->text('');
		$this->info('À ajouter dans config/master.config.php :');
		$this->text('');
		foreach ($lines as $line) {
			$this->text('    ' . cyan($line));
		}
		$this->text('');
	}

}
