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

/**
 * Provides coloured CLI output helpers for commands that use this trait.
 * Relies on ANSI escape codes — colour helper functions (bleu, vert, etc.)
 * must be defined in the global scope (config/bootstrap.php).
 */
trait CliMessage
{
	/** Prints a cyan info message. */
	public function info(string $message): void {
		echo "\033[36m$message \033[0m\n";
	}

	/** Prints an indented cyan info message. */
	public function infoT(string $message): void {
		$this->info(" \t$message");
	}

	/** Prints a red error message. */
	public function error(string $str): void {
		echo "\033[31m$str \033[0m\n";
	}

	/** Prints a green success message. */
	public function success(string $str): void {
		echo "\033[32m$str \033[0m\n";
	}

	/** Prints a yellow warning message. */
	public function warning(string $str): void {
		echo "\033[33m$str \033[0m\n";
	}

	/** Prints a plain text line. */
	public function text(string $str): void {
		echo "$str \n";
	}

	/** Prints a horizontal separator line. */
	public function line(): void {
		$this->info('────────────────────────────────');
	}

	/**
	 * Displays a numbered choice list and returns the user's input.
	 * @param  array  $choice key => label pairs
	 * @param  string $message prompt shown to the user
	 * @return string the raw readline response
	 */
	public function choice(array $choice, string $message = ' ? '): string {
		$this->line();
		foreach ($choice as $k => $v) {
			$this->text(orange($k) . ' : ' . cyan($v));
		}
		return readline($message);
	}

	/** Prompts the user with a question and returns the raw input. */
	public function ask(string $str): string {
		return readline($str . ' ? ');
	}

	/** Prompts the user for a yes/no confirmation and returns the raw input. */
	public function valide(string $str): string {
		return readline($str . ' ? (y/n) ');
	}

	/**
	 * Pretty-prints an array to the CLI with colour-coded types.
	 * @param array  $data         the array to display
	 * @param string $prefix       indentation prefix (used recursively)
	 * @param bool   $isFirstLevel controls opening/closing bracket output
	 */
	public function array(array $data, string $prefix = "  ", bool $isFirstLevel = true): void {
		if ($isFirstLevel) {
			echo "[\n";
		}

		$lastKey = array_key_last($data);
		foreach ($data as $key => $value) {
			echo $prefix . $prefix;

			if (is_int($key)) {
				echo bleu("[$key]") . " => ";
			} else {
				echo bleu($key) . " => ";
			}

			if (is_array($value)) {
				echo "[\n";
				$this->array($value, $prefix . "  ", false);
			} elseif (is_object($value)) {
				echo cyanVif("object(") . vertVif(get_class($value)) . cyanVif(")");
			} elseif (is_bool($value)) {
				echo $value ? bleuVif('true') : rouge('false');
			} elseif (is_null($value)) {
				echo orange('null');
			} elseif (is_string($value)) {
				echo vert('"' . $value . '"');
			} else {
				echo vertVif($value);
			}

			if ($key !== $lastKey) {
				echo ",";
			}

			echo "\n";
		}

		if ($isFirstLevel) {
			echo "]\n";
		} else {
			echo $prefix . "]";
		}
	}
}
