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
 * Validates that the field value matches a companion `{field}_confirmation` field.
 * Typically used for password confirmation inputs.
 */
class Confirmed extends Rule {

	public function check(string $field, mixed $value, array $data = []): bool {
		$confirmValue = $data[$field . '_confirmation'] ?? null;
		return $value === $confirmValue;
	}

	public function message(string $field): string {
		return "La confirmation du champ {$field} ne correspond pas";
	}
}