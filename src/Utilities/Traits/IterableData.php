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

namespace SkankyDev\Utilities\Traits;


trait IterableData {

	/**
	 * Rewind the Iterator to the first element
	 */
	public function rewind(): void{
		reset($this->data);
	}

	/**
	 * Return the current element
	 * @return mixed value
	 */
	public function current(): mixed{
		$var = current($this->data);
		return $var;
	}

	/**
	 * Return the key of the current element
	 * @return mixed key
	 */
	public function key(): mixed{
		$var = key($this->data);
		return $var;
	}

	/**
	 * Move forward to next element
	 * @return mixed value
	 */
	public function next(): void{
		$var = next($this->data);
		//return $var;
	}

	/**
	 * Checks if current position is valid
	 * @return bool valid
	 */
	public function valid(): bool{
		$key = key($this->data);
		$var = ($key !== null && $key !== false);
		return $var;
	}
}