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


/**
 * Validates that the field value is strictly equal to another named field.
 * Similar to Confirmed but the target field name is explicit: `same:other_field`.
 */
class Same extends Rule {

	public function __construct(protected string $otherField) {}

	public function check(string $field, mixed $value, array $data = []): bool {
		return $value === $this->getValue($this->otherField, $data);
	}

	public function message(string $field): string {
		return "Le champ {$field} doit être identique à {$this->otherField}";
	}
}