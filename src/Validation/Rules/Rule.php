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

/**
 * Base class for all validation rules.
 * Concrete rules implement check() with the actual validation logic
 * and message() with the human-readable error string.
 * Rules are optional by default: check() must return true when the value is empty
 * (unless the rule itself is Required).
 */
abstract class Rule {

	/** Returns true if the field value passes the rule. */
	abstract public function check(string $field, mixed $value, array $data = []): bool;

	/** Returns the human-readable error message for this rule and field. */
	abstract public function message(string $field): string;

	/**
	 * Retrieves a field's value from the full data set.
	 * Used by cross-field rules (Same, Confirmed).
	 */
	protected function getValue(string $field, array $data): mixed {
		return $data[$field] ?? null;
	}

	/**
	 * Returns true if the value is considered empty:
	 * null, empty string (after trim), or empty array.
	 */
	protected function isEmpty(mixed $value): bool {
		if (is_null($value)) {
			return true;
		}
		if (is_string($value) && trim($value) === '') {
			return true;
		}
		if (is_array($value) && empty($value)) {
			return true;
		}
		return false;
	}
}