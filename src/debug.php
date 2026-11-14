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

// Chaque fonction est protégée par function_exists() : un projet peut définir
// sa propre version, à condition de la charger AVANT l'autoload de Composer.

//ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ
if (!function_exists('dd')) {
	function dd($data,$message = ''){
		dump($data,$message);
		die();
	}
}

if (!function_exists('dump')) {
	function dump($data,$message = ''){
		echo $message;
		var_dump($data);
	}
}

if (!function_exists('debug')) {
	/**
	 * Echoes a coloured, bounded HTML dump of $data.
	 * @param string $message replaces the default "debug : type from …" header
	 * @param array  $limits  overrides of Dumper::LIMITS (`depth`, `items`, `string`, `nodes`)
	 */
	function debug($data,$message = '',array $limits = []){
		echo '<div class="debug-message">';
		if(!empty($message)){
			echo $message;
		}else{
			$backtrace = debug_backtrace();
			$type = gettype($backtrace[0]['args'][0]);
			if($type === 'object'){
				$type = get_class($backtrace[0]['args'][0]);
			}
			$source = '';
			if(isset($backtrace[1]['class'])){
				$source = 'class: <span class="debug-keyword">'.$backtrace[1]['class'].'</span> method: <span class="debug-keyword"> '.$backtrace[1]['function'].'</span>';
			}else{
				$source = 'file: <span class="debug-keyword">'.$backtrace[0]['file'].'</span> line: <span class="debug-keyword">'.$backtrace[0]['line'].'</span>';
			}
			echo 'debug : <span class="debug-keyword">'.$type.'</span> from '.$source;
		}

		// Bounded dump (depth, items, string length…): see Dumper::LIMITS
		echo "<pre>".\SkankyDev\Utilities\Dumper::html($data, $limits)."</pre>";
		echo '</div>';

	}
}
