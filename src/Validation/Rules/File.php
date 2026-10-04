<?php

namespace SkankyDev\Validation\Rules;

use SkankyDev\Http\UploadedFile;

/**
 * `file` : the value must be a successfully uploaded file (or a list of them).
 * An empty field passes: combine with `required` to make the file mandatory.
 * The base class of the other file rules (image, mimes, max_size).
 */
class File extends Rule {

	protected ?string $error = null;

	public function check(string $field, mixed $value, array $data = []): bool {
		if ($this->isEmpty($value)) {
			return true;
		}
		foreach ($this->files($value) as $file) {
			if (!$file instanceof UploadedFile || !$file->isValid()) {
				$this->error = $file instanceof UploadedFile ? $file->errorMessage() : null;
				return false;
			}
			if (!$this->checkFile($file)) {
				return false;
			}
		}
		return true;
	}

	/** Extra check on one valid file, overridden by the specialized rules. */
	protected function checkFile(UploadedFile $file): bool {
		return true;
	}

	/** A multiple field gives a list: every file must pass. */
	protected function files(mixed $value): array {
		return is_array($value) ? $value : [$value];
	}

	public function message(string $field): string {
		return $this->error ?? "Le champ {$field} doit être un fichier valide";
	}
}
