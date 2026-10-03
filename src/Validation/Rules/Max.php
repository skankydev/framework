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


/** Validates that the numeric value is less than or equal to a maximum. Passes on empty values. */
class Max extends Rule {
    public function __construct(protected float $max) {}
    
    public function check(string $field, mixed $value, array $data = []): bool {
        if ($this->isEmpty($value)) {
            return true;
        }
        
        return (float) $value <= $this->max;
    }
    
    public function message(string $field): string {
        return "Le champ {$field} doit être inférieur ou égal à {$this->max}";
    }
}
