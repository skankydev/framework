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

namespace SkankyDev\Http;

use SkankyDev\Config\Config;
use SkankyDev\Model\Document\StoredFile;

/**
 * One file of an upload request (one entry of $_FILES), as returned by Request::file().
 *
 * Security: nothing sent by the browser is trusted. The mime type is read from the
 * file content (finfo) and the stored extension is derived from that mime type,
 * never from the client file name — otherwise `photo.php` would land in public/
 * as an executable script.
 */
class UploadedFile {

	/**
	 * Real mime type → extension used when storing. A mime type missing from this
	 * map is stored as `.bin`, which the web server never executes.
	 * SVG is deliberately absent: it can carry JavaScript (XSS when served inline).
	 */
	const MIME_EXTENSIONS = [
		'image/jpeg'       => 'jpg',
		'image/png'        => 'png',
		'image/gif'        => 'gif',
		'image/webp'       => 'webp',
		'image/avif'       => 'avif',
		'application/pdf'  => 'pdf',
		'text/plain'       => 'txt',
		'text/csv'         => 'csv',
		'application/json' => 'json',
		'application/zip'  => 'zip',
	];

	const ERROR_MESSAGES = [
		UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur',
		UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
		UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement envoyé',
		UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoyé',
		UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur',
		UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque',
		UPLOAD_ERR_EXTENSION  => 'Envoi bloqué par une extension PHP',
	];

	private ?string $mime = null;
	private array|false|null $imageSize = null;

	/**
	 * @param bool $test true skips the is_uploaded_file() check and moves with rename()
	 *                   instead of move_uploaded_file(), so tests can use plain temp files.
	 */
	public function __construct(
		private string $clientName,
		private string $tmpName,
		private int    $error = UPLOAD_ERR_OK,
		private int    $size  = 0,
		private bool   $test  = false,
	) {}

	/** Builds an instance from one normalized $_FILES entry (name, type, tmp_name, error, size). */
	public static function fromArray(array $file, bool $test = false): self {
		return new self(
			(string) ($file['name'] ?? ''),
			(string) ($file['tmp_name'] ?? ''),
			(int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
			(int) ($file['size'] ?? 0),
			$test,
		);
	}

	/** False when the field was left empty (UPLOAD_ERR_NO_FILE). */
	public function isUploaded(): bool {
		return $this->error !== UPLOAD_ERR_NO_FILE;
	}

	/** True when the upload succeeded and the temp file really comes from an HTTP upload. */
	public function isValid(): bool {
		if ($this->error !== UPLOAD_ERR_OK || $this->tmpName === '') {
			return false;
		}
		return $this->test ? is_file($this->tmpName) : is_uploaded_file($this->tmpName);
	}

	/** PHP upload error code (UPLOAD_ERR_*). */
	public function error(): int {
		return $this->error;
	}

	/** Human-readable message for the upload error, null when there is none. */
	public function errorMessage(): ?string {
		if ($this->error === UPLOAD_ERR_OK) {
			return $this->isValid() ? null : 'Fichier envoyé invalide';
		}
		return self::ERROR_MESSAGES[$this->error] ?? 'Erreur inconnue lors de l\'envoi';
	}

	/** File name as sent by the browser: display only, never use it as a path. */
	public function clientName(): string {
		return $this->clientName;
	}

	/** Extension of the client file name: informative only, see extension(). */
	public function clientExtension(): string {
		return strtolower(pathinfo($this->clientName, PATHINFO_EXTENSION));
	}

	/** Size in bytes. */
	public function size(): int {
		return $this->size;
	}

	/** Temp path of the uploaded file. */
	public function path(): string {
		return $this->tmpName;
	}

	/** Mime type read from the file content (finfo), not the one sent by the browser. */
	public function mimeType(): string {
		if ($this->mime === null) {
			$mime = is_file($this->tmpName) ? (new \finfo(FILEINFO_MIME_TYPE))->file($this->tmpName) : false;
			$this->mime = $mime ?: 'application/octet-stream';
		}
		return $this->mime;
	}

	/** Safe extension derived from the real mime type (`bin` when unknown). */
	public function extension(): string {
		return self::MIME_EXTENSIONS[$this->mimeType()] ?? 'bin';
	}

	/** True when the content is an image that GD/getimagesize can read. */
	public function isImage(): bool {
		return str_starts_with($this->mimeType(), 'image/') && $this->readImageSize() !== false;
	}

	/** [width, height] of an image, null for anything else. */
	public function dimensions(): ?array {
		$info = $this->isImage() ? $this->readImageSize() : false;
		return $info ? [$info[0], $info[1]] : null;
	}

	/**
	 * Moves the file into a sub folder of the upload folder (config `upload.folder`,
	 * created if needed) under a random name and its safe extension, then describes it
	 * as a StoredFile ready to be embedded in a document.
	 * @param string      $subdir folder relative to `upload.folder`, e.g. `img` or `media/2026` ('' = root)
	 * @param string|null $name   base name without extension (random when null)
	 * @throws \RuntimeException when the upload is invalid, the folder escapes `upload.folder` or the move fails
	 */
	public function store(string $subdir = '', ?string $name = null): StoredFile {
		if (!$this->isValid()) {
			throw new \RuntimeException($this->errorMessage() ?? 'Fichier envoyé invalide');
		}

		$subdir = trim(str_replace('\\', '/', $subdir), '/');
		if (in_array('..', explode('/', $subdir), true)) {
			throw new \RuntimeException("Sous-dossier d'upload invalide : {$subdir}");
		}
		if ($name !== null && ($name === '' || preg_match('#[/\\\\]|\.\.#', $name))) {
			throw new \RuntimeException("Nom de fichier invalide : {$name}");
		}

		$dir = rtrim(Config::get('upload.folder'), '/\\') . ($subdir !== '' ? DS . str_replace('/', DS, $subdir) : '');
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new \RuntimeException("Impossible de créer le dossier {$dir}");
		}

		// read everything that needs the temp file before moving it
		$mime       = $this->mimeType();
		$dimensions = $this->dimensions();
		$filename   = ($name ?? bin2hex(random_bytes(8))) . '.' . $this->extension();
		$target     = $dir . DS . $filename;

		$moved = $this->test ? rename($this->tmpName, $target) : move_uploaded_file($this->tmpName, $target);
		if (!$moved) {
			throw new \RuntimeException("Impossible de déplacer le fichier vers {$target}");
		}

		return new StoredFile([
			'path'          => ($subdir !== '' ? $subdir . '/' : '') . $filename,
			'original_name' => $this->clientName,
			'mime'          => $mime,
			'size'          => $this->size,
			'width'         => $dimensions[0] ?? null,
			'height'        => $dimensions[1] ?? null,
		]);
	}

	private function readImageSize(): array|false {
		if ($this->imageSize === null) {
			$this->imageSize = is_file($this->tmpName) ? @getimagesize($this->tmpName) : false;
		}
		return $this->imageSize;
	}
}
