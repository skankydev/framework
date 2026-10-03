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

/** Validates that the string length (mb_strlen) is at least $min characters. Passes on empty values. */
class MinLength extends Rule {

    public function __construct(protected int $min) {}
    
    public function check(string $field, mixed $value, array $data = []): bool {
        if ($this->isEmpty($value)) {
            return true;
        }
        
        return mb_strlen((string) $value) >= $this->min;
    }
    
    public function message(string $field): string {
        return "Le champ {$field} doit contenir au moins {$this->min} caractères";
    }

}