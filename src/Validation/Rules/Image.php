<?php

namespace SkankyDev\Validation\Rules;

use SkankyDev\Http\UploadedFile;

/** `image` : the uploaded file must be a readable image (checked on its content). */
class Image extends File {

	protected function checkFile(UploadedFile $file): bool {
		return $file->isImage();
	}

	public function message(string $field): string {
		return $this->error ?? "Le champ {$field} doit être une image";
	}
}
