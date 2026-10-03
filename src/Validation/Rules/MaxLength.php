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

namespace SkankyDev\Validation\Rules;

use SkankyDev\Validation\Rules\Rule;

/** Validates that the string length (mb_strlen) does not exceed $max characters. Passes on empty values. */
class MaxLength extends Rule {

	public function __construct(protected int $max) {}
	
	public function check(string $field, mixed $value, array $data = []): bool {
		if ($this->isEmpty($value)) {
			return true;
		}
		
		return mb_strlen((string) $value) <= $this->max;
	}
	
	public function message(string $field): string {
		return "Le champ {$field} doit contenir maximum {$this->max} caractères";
	}
}