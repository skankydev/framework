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

namespace SkankyDev\Config;


use SkankyDev\Utilities\Traits\ArrayPathable;

/**
 * app Config 
 */
class Config {

	use ArrayPathable;

	static $conf;
	
	static function getDbConf($dbSelec = 'default'){
		return self::arrayGet('db.'.$dbSelec,self::$conf);
	}

	static function getModuleList(){
		return self::arrayGet('Module',self::$conf);
	}

	static function getDefaultNamespace(){
		return self::arrayGet('default.namespace',self::$conf);
	}
	static function getDefaultAction(){
		return self::arrayGet('default.action',self::$conf);
	}

	static function getVersion(){
		return self::arrayGet('skankydev.version',self::$conf);
	}

	static function getCurrentNamespace(){
		$name = self::get('skankydev.curentNamespace');
		if(!$name){
			$name = self::getDefaultNamespace();
		}
		return $name;
	}

	static function setCurrentNamespace($name){
		return self::set('skankydev.curentNamespace',$name);
	}

	static function get($path){
		return self::arrayGet($path,self::$conf);
	}

	static function set($path,$value){
		return self::arraySet($path,$value,self::$conf);
	}

	static function getDebug(){
		return self::arrayGet('debug',self::$conf);
	}

	static function print(){
		debug(self::$conf);
	}

	static function initConf($basePath = ''){

		if(empty(self::$conf)){
			if(empty($basePath)){
				$basePath = APP_FOLDER;
			}
			$mConf = require $basePath.DS.'config'.DS.'master.config.php';
			$modulesConf = [];
			foreach ($mConf['Module'] as $module) {
				$file = $basePath.DS.'src'.DS.$module.DS.'Config'.DS.'config.php';
				if(!file_exists($file)){
					continue;
				}
				$modulesConf = array_replace_recursive($modulesConf, require $file);
			}
			$dConf = require SKANKY_FOLDER.DS.'Config'.DS.'default.config.php';
			// Priority, lowest to highest: framework defaults < modules (in declared order) < project master config.
			self::$conf = array_replace_recursive($dConf, $modulesConf, $mConf);
		}
		return self::$conf;
	}

}
