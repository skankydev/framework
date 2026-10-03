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
use SkankyDev\Model\MasterCollection;

/**
 * Crée/synchronise les index déclarés par chaque Collection (cf. MasterCollection::getIndexes()).
 * Découvre toutes les classes `*Collection` dans le dossier `Model/` de chaque module
 * déclaré en config + SkankyDev lui-même (même mécanisme que l'auto-découverte des commandes).
 * Idempotent, à rejouer sans risque à chaque déploiement.
 */
class DbSync extends MasterCommand {

	static protected string $signature = 'db-sync';
	static protected string $help = 'Crée/synchronise les index Mongo déclarés par les Collections';

	public function run(array $arg = []): void {
		$this->info('🔧 Synchronisation des index...');

		$found = 0;
		foreach ($this->collectionClasses() as $class) {
			$found++;
			/** @var MasterCollection $collection */
			$collection = $class::getInstance();
			$indexes = $collection->getIndexes();

			$snapshots = $collection->snapshotTargets();

			if (empty($indexes) && empty($snapshots)) {
				continue;
			}

			$collection->syncIndexes();
			$this->success("✓ {$class} : " . count($indexes) . ' index' . ($snapshots ? ', ' . count($snapshots) . ' snapshot(s)' : ''));
		}

		if ($found === 0) {
			$this->warning('Aucune Collection trouvée.');
			return;
		}

		$this->info('Terminé.');
	}

	/**
	 * Scanne le dossier `Model/` de chaque module (+ SkankyDev) et retourne les FQCN
	 * des classes `*Collection.php` qui étendent MasterCollection.
	 * @return string[]
	 */
	private function collectionClasses(): array {
		$modules = Config::get('Module');
		$modules[] = 'SkankyDev';

		$classes = [];
		foreach ($modules as $module) {
			$dir = ($module === 'SkankyDev' ? SKANKY_FOLDER : SRC_FOLDER . DS . $module) . DS . 'Model';
			if (!is_dir($dir)) {
				continue;
			}

			foreach (scandir($dir) as $file) {
				if (!str_ends_with($file, 'Collection.php') || $file === 'MasterCollection.php') {
					continue;
				}

				$className = $module . '\\Model\\' . str_replace('.php', '', $file);
				if (class_exists($className) && is_subclass_of($className, MasterCollection::class)) {
					$classes[] = $className;
				}
			}
		}

		return $classes;
	}

}
