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

namespace SkankyDev\Validation;

use SkankyDev\Validation\Rules\Rule;
use SkankyDev\Config\Config;

/**
 * Runs a set of validation rules against a data array.
 * Rules are declared as `field => 'rule1|rule2:param'` strings or arrays.
 * Rule classes are resolved from the `class.rules` config key.
 */
class Validator {

	protected array $data         = [];
	protected array $rules        = [];
	protected array $errors       = [];
	protected array $ruleRegistry = [];

	/**
	 * @param array $rules field => rule string/array pairs
	 * @param array $data  the input data to validate
	 */
	public function __construct(array $rules = [], array $data = []) {
		$this->data         = $data;
		$this->rules        = $rules;
		$this->ruleRegistry = Config::get('class.rules');
	}

	/** Replaces the data set to validate. */
	public function setData(array $data): self {
		$this->data = $data;
		return $this;
	}

	/** Replaces the rule set. */
	public function setRules(array $rules): self {
		$this->rules = $rules;
		return $this;
	}

	/**
	 * Runs all rules against the current data.
	 * Stops at the first failing rule per field (fail-fast).
	 * @return bool true if all fields pass, false if any error was recorded
	 */
	public function validate(): bool {
		$this->errors = [];

		foreach ($this->rules as $field => $rules) {
			$value = $this->data[$field] ?? null;
			foreach ($this->makeRules($rules) as $rule) {
				if (!$rule->check($field, $value, $this->data)) {
					$this->errors[$field][] = $rule->message($field);
					break;
				}
			}
		}

		return empty($this->errors);
	}

	/**
	 * Parses a pipe-delimited rule string (or array) into Rule instances.
	 * @param  string|array $rules e.g. `'required|min:3'` or `['required', 'min:3']`
	 * @return Rule[]
	 */
	protected function makeRules(string|array $rules): array {
		if (is_string($rules)) {
			$rules = explode('|', $rules);
		}
		return array_map(fn($r) => $this->createRule($r), $rules);
	}

	/**
	 * Instantiates a single Rule from its string descriptor.
	 * Supports parameterised rules: `min:3` or `in:1,2,3`.
	 * @throws \Exception if the rule name is not found in the registry
	 */
	protected function createRule(string $rule): Rule {
		if (str_contains($rule, ':')) {
			[$name, $paramStr] = explode(':', $rule, 2);
			$params = explode(',', $paramStr);
		} else {
			$name   = $rule;
			$params = [];
		}

		$class = $this->ruleRegistry[$name] ?? null;

		if (!$class) {
			throw new \Exception("Unknown validation rule: {$name}");
		}

		return new $class(...$params);
	}

	/** Returns all errors keyed by field name. */
	public function errors(): array {
		return $this->errors;
	}

	/** Returns the first error message for a field, or null if none. */
	public function error(string $field): ?string {
		return $this->errors[$field][0] ?? null;
	}

	/** Returns true if the field has at least one error. */
	public function hasError(string $field): bool {
		return isset($this->errors[$field]);
	}
}