<?php

namespace SkankyDev\Validation\Rules;

use SkankyDev\Http\UploadedFile;

/**
 * `mimes:jpg,png,webp` : the extension derived from the real mime type
 * (UploadedFile::extension(), not the client file name) must be in the list.
 */
class Mimes extends File {

	protected array $extensions;

	public function __construct(string ...$extensions) {
		$this->extensions = array_map(fn($e) => strtolower(trim($e)) === 'jpeg' ? 'jpg' : strtolower(trim($e)), $extensions);
	}

	protected function checkFile(UploadedFile $file): bool {
		return in_array($file->extension(), $this->extensions, true);
	}

	public function message(string $field): string {
		return $this->error ?? __('skankydev.validation.mimes', ['field' => $field, 'extensions' => implode(', ', $this->extensions)]);
	}
}
