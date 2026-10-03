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

use SkankyDev\Command\MasterCommand;
use SkankyDev\Config\Config;
use SkankyDev\Utilities\Traits\StringFacility;

class CrudMaker extends MasterCommand
{

	use StringFacility;
	
	static protected string $signature = 'crud-maker';
	static protected string $help = 'Crée les differante class et fichier pour fair un crud complet';

	private array $fields   = [];
	private array $variable = [];
	private array $folders  = [];
	private array $files    = [];

	private string $documentName;

	function __construct(){

	}

	/**
	 * Entry point of the command. Expects the document name as first argument.
	 * Runs the interactive field configuration then generates all CRUD files.
	 * @param array $arg parsed argv, index 0 must be the document name e.g. `Article`
	 */
	function run(array $arg = []) :void{
		$this->info('═══════════════════════════════════════');
		$this->success('    CRUD Maker - SkankyDev');
		$this->info('═══════════════════════════════════════');
		$this->text('');

		
		if(!isset($arg[0])){
			$this->error('Usage: php craft crud-maker <DocumentName>');
			return;
		}
		$this->documentName = $arg[0];
		$this->initVariables();
		$this->initFolders();
		$this->initFiles();
		$this->initFields();
		/*$this->array($this->folders);
		$this->array($this->variable);*/
		//exit;
		$this->generateFiles();
	}

	/**
	 * Interactively prompts the user to define the fields of the document.
	 * Each field has a name, a type and a required flag.
	 * Loop ends when the user submits an empty field name.
	 */
	private function initFields(): void {
		$this->warning('Configuration des champs de '.$this->documentName.' :');
		$this->text('');
		
		$types = ['string', 'int', 'float', 'bool', 'date', 'datetime', 'array', 'ObjectId'];
		
		while (true) {
			$fieldName = $this->ask(vert('Nom du champ').' ('.rouge('vide pour terminer').')');
			
			if (empty($fieldName)) {
				break;
			}

			$choix = $this->choice($types,vert('Type du champ')." ? ");
			if (!isset($types[$choix])) {
				$this->error("Type invalide !");
				continue;
			}

			$fieldType = $types[$choix];

			$required = $this->valide('Requis') === 'y';
			
			$this->fields[] = [
				'name' => $fieldName,
				'type' => $fieldType,
				'required' => $required
			];
			
			$this->success("✓ Champ '$fieldName' ajouté");
			$this->text('');
		}
		
		if (empty($this->fields)) {
			$this->error('Aucun champ configuré !');
			//exit;
		}
	}

	/**
	 * Derives the related document name (PascalCase, singular) from a FK field name.
	 * e.g. `project_id` → `Project` (used for the `ObjectId` field type).
	 */
	private function fkRelated(string $fieldName): string {
		return $this->toCap(preg_replace('/_id$/', '', $fieldName), '_');
	}

	/**
	 * Name of the first non-FK field, used as the human label in the show view
	 * header and the breadcrumb. Falls back to the first field, then `_id`.
	 */
	private function labelField(): string {
		foreach ($this->fields as $field) {
			if ($field['type'] !== 'ObjectId') {
				return $field['name'];
			}
		}
		return $this->fields[0]['name'] ?? '_id';
	}

	/**
	 * Builds the template variable map from the document name:
	 * singular/plural forms, camelCase, dash-case and collection name.
	 */
	private function initVariables(): void {
		$this->variable = [
			'name'          => $this->documentName,
			'singular'      => $this->singularize($this->documentName),
			'plural'        => $this->pluralize($this->documentName),
			'singularCamel' => $this->toCamel($this->singularize($this->documentName)),
			'pluralCamel'   => $this->toCamel($this->pluralize($this->documentName)),
			'dashed'        => $this->toDash($this->documentName),
			'collection'    => $this->pluralize($this->toDash($this->documentName,'_')),
		];
	}

	/**
	 * Builds the destination folder map for each generated file type.
	 */
	private function initFolders(): void{
			$this->folders = [
				'Document'   => SRC_FOLDER.DS.'App'.DS.'Model'.DS.'Document',
				'Collection' => SRC_FOLDER.DS.'App'.DS.'Model',
				'Controller' => SRC_FOLDER.DS.'App'.DS.'Controller',
				'Form'       => SRC_FOLDER.DS.'App'.DS.'Form',
				'view/index' => VIEW_FOLDER.DS.$this->variable['dashed'],
				'view/create'=> VIEW_FOLDER.DS.$this->variable['dashed'],
				'view/edit'  => VIEW_FOLDER.DS.$this->variable['dashed'],
				'view/show'  => VIEW_FOLDER.DS.$this->variable['dashed'],
			];
	}

	/**
	 * Builds the destination filename map for each generated file type.
	 */
	private function initFiles(): void {
		$this->files = [
			'Document'    => $this->variable['name'].'.php',
			'Collection'  => $this->variable['name'].'Collection.php',
			'Controller'  => $this->variable['name'].'Controller.php',
			'Form'        => $this->variable['name'].'Form.php',
			'view/index'  => 'index.php',
			'view/create' => 'create.php',
			'view/edit'   => 'edit.php',
			'view/show'   => 'show.php',
		];
	}

	/**
	 * Iterates over all file types and triggers generation for each one.
	 */
	private function generateFiles(): void {
		$this->warning('Génération des fichiers...');
		$this->text('');

		// Document
		$this->generateFromTemplate('Document', 'document');

		// Collection
		$this->generateFromTemplate('Collection', 'collection');

		// Controller
		$this->generateFromTemplate('Controller', 'controller');

		// Form
		$this->generateFromTemplate('Form', 'form');

		// Views
		$this->generateFromTemplate('view/index', 'view');
		$this->generateFromTemplate('view/create', 'view');
		$this->generateFromTemplate('view/edit', 'view');
		$this->generateFromTemplate('view/show', 'view');

		$this->text('');
		$this->success('✓ CRUD généré avec succès !');
	}

	/**
	 * Renders a template and writes the result to its destination file.
	 * Replaces `%?php`/`?%`/`%?=` pseudo-tags with real PHP tags after rendering,
	 * to prevent PHP from executing template code during generation.
	 * Prompts for confirmation before overwriting an existing file.
	 * @param string $template key identifying the file type (e.g. `Document`, `view/index`)
	 */
	private function generateFromTemplate(string $template): void {
		$templatePath = Config::get('template.folder') . DS . $template . '.php';

		// Déterminer le fichier de destination
		$destPath = $this->folders[$template] . DS . $this->files[$template];
		
		// Vérifier si le fichier existe déjà
		if (file_exists($destPath)) {
			$this->warning('⚠ Le fichier '.$this->files[$template].' existe déjà !');
			$confirm = $this->valide('Écraser le fichier');
			
			if ($confirm !== 'y') {
				$this->info('→ '.$destFile.' ignoré');
				return;
			}
		}
		

		extract($this->variable);
		// Capturer le rendu du template
		ob_start();
		include $templatePath;
		$content = ob_get_clean();

		// Remplacer les pseudo-balises par de vraies balises PHP
		$content = str_replace(['%?php', '?%'], ['<?php', '?>'], $content);
		$content = str_replace('%?=', '<?=', $content);

		// Créer le dossier si nécessaire
		if (!is_dir($this->folders[$template])) {
			mkdir($this->folders[$template], 0775, true);
		}

		// Écrire le fichier
		file_put_contents($destPath, $content);

		$this->success('✔️ '.$destPath.' généré');
	}
}
