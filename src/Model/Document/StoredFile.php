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

namespace SkankyDev\Model\Document;

use SkankyDev\Config\Config;
use SkankyDev\Http\Request;

/**
 * A file stored in the upload folder, as returned by UploadedFile::store(),
 * meant to be embedded in a document: `public ?StoredFile $cover = null;`.
 *
 * Only the path relative to the upload folder is persisted. The disk location
 * (`upload.folder`) and the public URL (`upload.url`) come from the config, so
 * moving the files or serving them from a CDN needs no data migration.
 */
class StoredFile extends EmbeddedDocument {

	/** Path relative to `upload.folder`, with `/` separators, e.g. `img/3f2a….jpg`. */
	public string $path = '';
	/** Name of the file on the client side, for display only. */
	public string $original_name = '';
	/** Mime type read from the content at upload time. */
	public string $mime = '';
	/** Size in bytes. */
	public int $size = 0;
	public ?int $width = null;
	public ?int $height = null;

	/**
	 * Public URL: `upload.url` + path. Absolute on demand, using the scheme and host
	 * of the current request (unless `upload.url` is already absolute, e.g. a CDN).
	 */
	public function url(bool $absolute = false): string {
		$url = rtrim((string) Config::get('upload.url'), '/') . '/' . $this->path;
		if (!$absolute || preg_match('#^(https?:)?//#', $url)) {
			return $url;
		}
		$request = Request::getInstance();
		return $request->scheme() . '://' . $request->host() . $url;
	}

	/** Absolute path on disk: `upload.folder` + path. */
	public function fullPath(): string {
		return rtrim((string) Config::get('upload.folder'), '/\\') . DS . str_replace('/', DS, $this->path);
	}

	public function isImage(): bool {
		return str_starts_with($this->mime, 'image/');
	}

	/** File extension, taken from the stored (safe) name. */
	public function extension(): string {
		return pathinfo($this->path, PATHINFO_EXTENSION);
	}

	/** Deletes the file from disk. Returns false if it was already gone. */
	public function delete(): bool {
		$file = $this->fullPath();
		return $this->path !== '' && is_file($file) && unlink($file);
	}

	/** Adds the computed URL to the JSON output. */
	public function jsonSerialize(): mixed {
		return [...parent::jsonSerialize(), 'url' => $this->url()];
	}
}
