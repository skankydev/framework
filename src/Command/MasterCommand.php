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

namespace SkankyDev\Command;

use SkankyDev\Utilities\Traits\CliMessage;
use ReflectionClass;


abstract class MasterCommand {

	use CliMessage;

	static protected string $signature = 'master';
	static protected string $help = 'La description';

	/**
	 * Returns the command metadata using Reflection on the called class.
	 * Reads the static `$signature` and `$help` properties of the concrete command.
	 * @return array{class: string, signature: string, help: string}
	 * @throws CommandException if `$signature` or `$help` are missing
	 */
	public static function getInfo(): array {
		$class = get_called_class();
		$mirror = new ReflectionClass($class);

		if (!$mirror->hasProperty('signature') || !$mirror->hasProperty('help') ) {
			throw new CommandException("La Command $className est mal defini.");
		}


		$signature = $mirror->getProperty('signature')->getValue();
		$help = $mirror->getProperty('help')->getValue();

		return [
        	'class'     => $class,
        	'signature' => $signature,
        	'help'      => $help,
        ];
    }

	/**
	 * Executes the command logic.
	 * @param array $arg parsed arguments from argv (without the command key)
	 */
	abstract public function run(array $arg = []): void;


}