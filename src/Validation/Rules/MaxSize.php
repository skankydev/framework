<?php

namespace SkankyDev\Validation\Rules;

use SkankyDev\Http\UploadedFile;

/** `max_size:2048` : the uploaded file must not exceed this size, in kilobytes. */
class MaxSize extends File {

	public function __construct(protected float $kilobytes) {}

	protected function checkFile(UploadedFile $file): bool {
		return $file->size() <= $this->kilobytes * 1024;
	}

	public function message(string $field): string {
		return $this->error ?? "Le fichier {$field} ne doit pas dépasser {$this->kilobytes} Ko";
	}
}
