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

use SkankyDev\Utilities\Dumper;

// Chaque fonction est protégée par function_exists() : un projet peut définir
// sa propre version, à condition de la charger AVANT l'autoload de Composer.

//ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ
if (!function_exists('dd')) {
	function dd($data,$message = ''){
		debug($data,$message);
		die();
	}
}

if (!function_exists('dump')) {
	function dump($data,$message = ''){
		echo $message;
		debug($data);
	}
}

if (!function_exists('debug')) {
	/**
	 * Echoes a coloured, bounded dump of $data: an HTML block (`debug-*` CSS classes)
	 * on a page, ANSI-coloured text in a terminal (see Dumper::isCli()).
	 * @param string $message replaces the default "debug : type from …" header
	 * @param array  $limits  overrides of Dumper::LIMITS (`depth`, `items`, `string`, `nodes`)
	 */
	function debug($data,$message = '',array $limits = []){
		if(!empty($message)){
			$header = $message;
		}else{
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
			// Called through dump() / dd(): the caller that matters is theirs, one frame up
			$call = 0;
			if(!isset($backtrace[1]['class']) && in_array($backtrace[1]['function'] ?? '', ['dump', 'dd'], true)){
				$call = 1;
			}
			if(isset($backtrace[$call + 1]['class'])){
				$source = 'class: '.Dumper::colour('keyword', $backtrace[$call + 1]['class']).' method: '.Dumper::colour('keyword', $backtrace[$call + 1]['function']);
			}else{
				$source = 'file: '.Dumper::colour('keyword', $backtrace[$call]['file']).' line: '.Dumper::colour('keyword', (string) $backtrace[$call]['line']);
			}
			$header = 'debug : '.Dumper::colour('keyword', get_debug_type($data)).' from '.$source;
		}

		// Bounded dump (depth, items, string length…): see Dumper::LIMITS
		if(Dumper::isCli()){
			echo $header."\n".Dumper::cli($data, $limits)."\n";
			return;
		}
		echo '<div class="debug-message">'.$header.'<pre>'.Dumper::html($data, $limits).'</pre></div>';
	}
}
