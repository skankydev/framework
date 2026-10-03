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

namespace SkankyDev\Utilities;

use Exception;
use Throwable;
use SkankyDev\Config\Config;

class Log {


	/**
	 * Writes an INFO entry to the daily context log file.
	 * @param string $context log file prefix, defaults to `skankydev`
	 */
	public static function info(string $message, string $context = 'skankydev'): void {
		$logFile = APP_FOLDER . '/logs/'.date('Y-m-d')."-{$context}.log";
		$formatted = sprintf(
			"[%s] INFO: %s\n",
			date('Y-m-d H:i:s'),
			$message
		);
		
		self::write($logFile, $formatted);
	}

	/**
	 * Writes a full exception (message, file, line, stack trace) to the daily error log.
	 * @param array<string,string> $context extra key/value lines logged just before the
	 *                                       stack trace (e.g. URL/IP/User-Agent)
	 */
	public static function error(Throwable $exception, array $context = []): void {
		$logFile = APP_FOLDER . '/logs/'.date('Y-m-d').'-error.log';

		$contextLines = '';
		foreach ($context as $key => $value) {
			$contextLines .= "{$key}: {$value}\n";
		}

		$message = sprintf(
			"[%s] %s: %s in %s:%d\n%sStack trace:\n%s\n\n",
			date('Y-m-d H:i:s'),
			get_class($exception),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$contextLines,
			$exception->getTraceAsString()
		);

		self::write($logFile, $message);
	}


	/**
	 * Writes a WARNING entry to the daily context log file.
	 * @param string $context log file prefix, defaults to `skankydev`
	 */
	public static function warning(string $message, string $context = 'skankydev'): void {
		$logFile = APP_FOLDER . '/logs/'.date('Y-m-d')."-{$context}.log";
		$formatted = sprintf(
			"[%s] WARNING: %s\n",
			date('Y-m-d H:i:s'),
			$message
		);
		
		self::write($logFile, $formatted);
	}

	/**
	 * Writes a job lifecycle event to the daily jobs log.
	 * @param string      $jobName FQCN of the job class
	 * @param string      $status  e.g. `queued`, `processing`, `completed`, `failed`
	 * @param string|null $details optional extra context
	 */
	public static function job(string $jobName, string $status, ?string $details = null): void {
		$logFile = APP_FOLDER . '/logs/'.date('Y-m-d').'-jobs.log';
		$message = sprintf(
			"[%s] [%s] %s",
			date('Y-m-d H:i:s'),
			strtoupper($status),
			$jobName
		);
		
		if ($details) {
			$message .= " - {$details}";
		}
		
		$message .= "\n";
		
		self::write($logFile, $message);
	}

	/**
	 * Writes a DEBUG entry to the daily debug log. No-op unless debug mode is on
	 * (Config `debug`, same switch as Application).
	 */
	public static function debug(string $message, array $context = []): void {
		// Ne log que si le mode debug est actif
		if (!Config::get('debug')) {
			return;
		}
		
		$logFile = APP_FOLDER . '/logs/'.date('Y-m-d').'-debug.log';
		
		$formatted = sprintf(
			"[%s] DEBUG: %s",
			date('Y-m-d H:i:s'),
			$message
		);
		
		if (!empty($context)) {
			$formatted .= "\nContext: " . json_encode($context, JSON_PRETTY_PRINT);
		}
		
		$formatted .= "\n\n";
		
		self::write($logFile, $formatted);
	}

	/**
	 * Appends a message to a log file, creating the directory if needed.
	 */
	private static function write(string $logFile, string $message): void {
		$logDir = dirname($logFile);
		
		if (!is_dir($logDir)) {
			mkdir($logDir, 0775, true);
		}
		
		file_put_contents($logFile, $message, FILE_APPEND);
	}

	/**
	 * Deletes log files older than $days days from the logs directory.
	 */
	public static function cleanup(int $days = 10): void {
		$logDir = APP_FOLDER . '/logs';
		
		if (!is_dir($logDir)) {
			return;
		}
		
		$files = glob($logDir . '/*.log');
		$limit = time() - ($days * 86400);
		
		foreach ($files as $file) {
			if (filemtime($file) < $limit) {
				unlink($file);
			}
		}
	}
}