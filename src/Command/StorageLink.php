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
 * Prépare le dossier storage/ du projet : crée les dossiers de la config
 * (`upload.folder`, `storage.private`, `storage.logs`, `storage.cache`) puis le
 * lien `upload.link` (public/upload) vers `upload.folder`, pour que le serveur
 * web serve les fichiers uploadés sans que storage/ soit dans public/.
 *
 * Sous Windows, le lien est une junction (`mklink /J`, sans droits admin) ;
 * ailleurs, un vrai symlink. Si `upload.link` est encore un vrai dossier
 * (ancienne organisation), son contenu est déplacé dans `upload.folder` après
 * confirmation : les StoredFile ne stockant qu'un chemin relatif, et l'URL
 * restant la même, rien n'est à migrer en base.
 *
 * Peut être relancée sans risque : ce qui est déjà en place est laissé tel quel.
 */
class StorageLink extends MasterCommand {

	static protected string $signature = 'storage-link';
	static protected string $help = 'skankydev.cli.storage_link.help';

	/** Pas de question : `-y` / `--yes` accepte le déplacement de l'ancien dossier. */
	private bool $yes = false;

	/**
	 * @param array $arg `-y` / `--yes` pour répondre oui au déplacement (déploiement scripté)
	 */
	public function run(array $arg = []): void {
		$this->yes = !empty($arg['y']) || !empty($arg['yes']);

		$this->info('═══════════════════════════════════════');
		$this->success('    Storage - SkankyDev');
		$this->info('═══════════════════════════════════════');
		$this->text('');

		foreach (['upload.folder', 'storage.private', 'storage.logs', 'storage.cache'] as $key) {
			$this->makeFolder((string) Config::get($key));
		}

		$this->line();
		if ($this->link((string) Config::get('upload.link'), (string) Config::get('upload.folder'))) {
			$this->text('');
			$this->success(__('skankydev.cli.done'));
		}
	}

	private function makeFolder(string $folder): void {
		if ($folder === '') {
			return;
		}
		if (is_dir($folder)) {
			$this->text(__('skankydev.cli.storage_link.folder_exists', ['path' => $this->relative($folder)]));
			return;
		}
		mkdir($folder, 0775, true);
		$this->success(__('skankydev.cli.storage_link.folder_created', ['path' => $this->relative($folder)]));
	}

	/**
	 * Crée le lien $link → $target. Retourne false si quelque chose bloque
	 * (le message d'erreur est déjà affiché).
	 */
	private function link(string $link, string $target): bool {
		$link = rtrim($link, '/\\');
		$target = realpath($target);
		$names = ['link' => $this->relative($link), 'target' => $this->relative((string) $target)];

		if ($target === false) {
			$this->error(__('skankydev.cli.storage_link.target_missing', $names));
			return false;
		}

		if (file_exists($link) || is_link($link)) {
			$resolved = realpath($link);
			if ($resolved === $target) {
				$this->success(__('skankydev.cli.storage_link.already_linked', $names));
				return true;
			}
			if (!is_dir($link) || $resolved !== $this->normalize($link)) {
				// un fichier, ou un lien qui pointe ailleurs : on ne touche à rien
				$this->error(__('skankydev.cli.storage_link.link_taken', $names));
				return false;
			}
			if (!$this->moveContent($link, $target, $names)) {
				return false;
			}
		}

		if (!$this->createLink($link, $target)) {
			$this->error(__('skankydev.cli.storage_link.link_failed', $names));
			return false;
		}
		$this->success(__('skankydev.cli.storage_link.linked', $names));
		return true;
	}

	/**
	 * $link est un vrai dossier (uploads d'avant storage/) : déplace son contenu
	 * dans $target puis le supprime, pour laisser la place au lien. Ne déplace
	 * rien si un seul élément existe déjà des deux côtés.
	 */
	private function moveContent(string $link, string $target, array $names): bool {
		$entries = array_values(array_diff(scandir($link), ['.', '..']));

		$conflicts = array_filter($entries, fn($e) => file_exists($target . DS . $e));
		if ($conflicts) {
			$this->error(__('skankydev.cli.storage_link.conflict', [...$names, 'list' => implode(', ', $conflicts)]));
			return false;
		}

		if ($entries) {
			$this->warning(__('skankydev.cli.storage_link.real_folder', [...$names, 'count' => count($entries)]));
			if (!$this->yes && strtolower(trim($this->valide(__('skankydev.cli.storage_link.move_confirm', $names)))) !== 'y') {
				$this->warning(__('skankydev.cli.storage_link.move_cancelled', $names));
				return false;
			}
			foreach ($entries as $entry) {
				if (!rename($link . DS . $entry, $target . DS . $entry)) {
					$this->error(__('skankydev.cli.storage_link.move_failed', ['path' => $this->relative($link . DS . $entry)]));
					return false;
				}
			}
			$this->success(__('skankydev.cli.storage_link.moved', [...$names, 'count' => count($entries)]));
		}

		return rmdir($link);
	}

	/** Junction sous Windows (pas besoin des droits admin), symlink ailleurs. */
	private function createLink(string $link, string $target): bool {
		$parent = dirname($link);
		if (!is_dir($parent)) {
			mkdir($parent, 0775, true);
		}
		if (PHP_OS_FAMILY === 'Windows') {
			exec('cmd /c mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($target) . ' 2>&1', $output, $code);
			return $code === 0 && realpath($link) === $target;
		}
		return @symlink($target, $link);
	}

	/** Chemin absolu de $path sans résoudre les liens (realpath() les suit). */
	private function normalize(string $path): string {
		return realpath(dirname($path)) . DS . basename($path);
	}

	/** Chemin affiché relatif au projet quand il est dedans. */
	private function relative(string $path): string {
		return str_starts_with($path, APP_FOLDER . DS) ? substr($path, strlen(APP_FOLDER) + 1) : $path;
	}

}
