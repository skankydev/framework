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

namespace SkankyDev\Cli\Tools;

use SkankyDev\Utilities\Traits\Singleton;

class ArgParser {

	use Singleton;


	private $name = false;
	private $options = [];


	/**
	 * Parses argv into an associative array.
	 *
	 * Conventions:
	 * - First argument without `-` → `command` key
	 * - `-h` → alias for `help`
	 * - `--key=value` or `--key value` → `['key' => 'value']`
	 * - `--flag` alone → `['flag' => true]`
	 * - Positional value without key → numerically indexed
	 *
	 * @param  array $arg raw argv (including script name at index 0)
	 * @return array      parsed options array
	 */
	public function pars(array $arg): array {
		unset($arg[0]);
		$arg = array_values($arg);

		$asso = [
			'-h' => 'help',
		];
		
		if(isset($arg[0]) && !str_starts_with($arg[0],'-')){
			$this->options['command'] = $arg[0];
			unset($arg[0]);
			$arg = array_values($arg);
		}
		while(!empty($arg)){
			$data = preg_split("/[ =]+/", $arg[0]);
			$key = $asso[$data[0]] ?? $data[0];
			if(isset($data[1])){
				$this->options[$key] = $data[1];
				unset($arg[0]);
				$arg = array_values($arg);
				continue;
			}
			if(isset($arg[1]) && !str_starts_with($arg[1],'-')){
				$this->options[$key] = $arg[1];
				unset($arg[0]);
				unset($arg[1]);
				$arg = array_values($arg);
				continue;
			}
			if(!str_starts_with($arg[0],'-')){
				$this->options[] = $arg[0];
				unset($arg[0]);
				$arg = array_values($arg);
				continue;
			}
			$this->options[$key] = true;
			unset($arg[0]);
			$arg = array_values($arg);
		}
		return $this->options;
	}

}