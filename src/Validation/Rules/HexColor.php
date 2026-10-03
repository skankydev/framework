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

/** Validates that the value is a 6-digit hex colour string (e.g. `#ff0000`). */
class HexColor extends Rule {

	public function check(string $field, mixed $value, array $data = []): bool {
		if ($this->isEmpty($value)) {
			return true;
		}
		return preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) === 1;
	}

	public function message(string $field): string {
		return "Le champ {$field} doit être une couleur hexadécimale valide";
	}
}
