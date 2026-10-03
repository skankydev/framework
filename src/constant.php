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


// Racine du projet applicatif = le dossier qui contient `vendor/`. On la déduit
// de l'emplacement de l'autoloader Composer ; un projet peut la définir lui-même
// avant de charger l'autoload.
if (!defined('APP_FOLDER')) {
	define('APP_FOLDER', dirname((new ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 3));
}

const TIME_SECOND = 1;
const TIME_MINUTE = 60;
const TIME_HOUR   = 3600;
const TIME_DAY    = 86400;
const TIME_WEEK   = 604800;
const TIME_MONTH  = 2592000;
const TIME_YEAR   = 31536000;

const DS = DIRECTORY_SEPARATOR;
const PUBLIC_FOLDER = APP_FOLDER.DS.'public';
const UPLOAD_FOLDER = PUBLIC_FOLDER.DS.'upload';
const VIEW_FOLDER = APP_FOLDER.DS.'src_front'.DS.'view';
const SRC_FOLDER = APP_FOLDER.DS.'src';
const TEMPLATE_FOLDER = APP_FOLDER.DS.'src_front'.DS.'template';

// Dossier du framework SkankyDev lui-même (contrairement à APP_FOLDER, qui
// pointe sur le projet applicatif). Sert de racine aux ressources par défaut
// du framework — cf. Publishable ci-dessous.
const SKANKY_FOLDER = __DIR__;

// Ressources par défaut du framework (vues d'erreur, futurs templates
// CrudMaker...) : chaque projet les utilise telles quelles tant qu'il ne les
// a pas "publiées" (copiées dans son propre dossier + config mise à jour
// pour pointer dessus) — pattern à la Laravel vendor:publish.
const PUBLISHABLE_FOLDER = SKANKY_FOLDER.DS.'Publishable';


