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

use SkankyDev\Http\Response;
use SkankyDev\Http\UrlBuilder;
use SkankyDev\Utilities\Session;
use SkankyDev\Utilities\Token;

// Chaque helper est protégé par function_exists() : un projet peut définir sa
// propre version, à condition de la charger AVANT l'autoload de Composer.

if (!function_exists('redirect')) {
	function redirect(array $link){
		$url = UrlBuilder::_build($link);
		$response = new Response();
		$response->status(302)->header('Location', $url);
		return $response;
	}
}

if (!function_exists('view')) {
	function view(string $name,array $data = []){
		$response = new Response($name,$data);
		return $response;
	}
}

if (!function_exists('response')) {
	function response(array $data = []){
		$response = new Response('',$data);
		return $response;
	}
}

if (!function_exists('notFound')) {
	/**
	 * Réponse 404 : passe par les vues normales (layout, JS, CSS…), contrairement
	 * aux pages d'erreur de l'ExceptionHandler — un 404 n'est pas un plantage,
	 * c'est un résultat attendu (ex: document non trouvé), l'appli tourne bien.
	 * Vue rendue : src_front/view/error/error404.php.
	 */
	function notFound(array $data = []) {
		$response = new Response('error.404', $data);
		$response->status(404);
		return $response;
	}
}

if (!function_exists('e')) {
	/**
	 * Échapper du HTML
	 */
	function e($value) {
		return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('asset')) {
	/**
	 * Afficher un asset
	 */
	function asset($path) {
		return '/assets/' . ltrim($path, '/');
	}
}

if (!function_exists('url')) {
	/**
	 * Générer une URL
	 * @param bool $absolute true pour une URL absolue (scheme + host) — nécessaire dans les mails
	 */
	function url(array $params, bool $absolute = false) {
		return UrlBuilder::_build($params, $absolute);
	}
}

if (!function_exists('since')) {
	/**
	 * Ancien timestamp en format lisible
	 */
	function since(\DateTime $date, bool $full = false): string
	{
	    $diff  = (new \DateTime())->diff($date);
	    $weeks = (int) floor($diff->d / 7);
	    $days  = $diff->d - $weeks * 7;

	    $parts = [
	        'y' => $diff->y,
	        'm' => $diff->m,
	        'w' => $weeks,
	        'd' => $days,
	        'h' => $diff->h,
	        'i' => $diff->i,
	        's' => $diff->s,
	    ];

	    $labels = [
	        'y' => _('year'),
	        'm' => _('month'),
	        'w' => _('week'),
	        'd' => _('day'),
	        'h' => _('hour'),
	        'i' => _('minute'),
	        's' => _('second'),
	    ];

	    $string = [];
	    foreach ($labels as $k => $label) {
	        if ($parts[$k]) {
	            $string[$k] = $parts[$k] . ' ' . $label . ($parts[$k] > 1 ? 's' : '');
	        }
	    }

	    if (!$full) {
	        $string = array_slice($string, 0, 1);
	    }

	    return $string ? implode(', ', $string) : _('just now');
	}
}

if (!function_exists('flash')) {
	/**
	 * Flash message
	 */
	function flash($type = 'success',$message = null) {
		if ($message === null) {
			return Session::getAndClean('flash');
		}
		Session::insert('flash', ['type' => $type,'message' => $message,]);
	}
}

if (!function_exists('csrf_token')) {
	/**
	 * Token CSRF de la session, généré une seule fois et conservé tant que la session vit
	 * (pattern synchronizer token). Le même token sert à tous les forms et à l'AJAX.
	 */
	function csrf_token(): string {
		$token = Session::get('csrf_token');
		if (!is_string($token) || $token === '') {
			$token = (new Token())->getToken();
			Session::set('csrf_token', $token);
		}
		return $token;
	}
}

if (!function_exists('csrf_field')) {
	function csrf_field() {
		return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
	}
}

if (!function_exists('old')) {
	function old($key, $default = '') {
		return Session::get('old.' . $key) ?? $default;
	}
}

if (!function_exists('error')) {
	function error($key) {
		$errors = Session::get('errors') ?? [];
		if (isset($errors[$key])) {
			return '<span class="error">' . e($errors[$key]) . '</span>';
		}
		return '';
	}
}

if (!function_exists('json')) {
	function json(array|object $data):string {
		return json_encode($data,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
	}
}


function noir(string $message){return "\033[30m".$message."\033[0m";}
function rouge(string $message){return "\033[31m".$message."\033[0m";}
function vert(string $message){return "\033[32m".$message."\033[0m";}
function jaune(string $message){return "\033[33m".$message."\033[0m";}
function bleu(string $message){return "\033[34m".$message."\033[0m";}
function violet(string $message){return "\033[35m".$message."\033[0m";}
function cyan(string $message){return "\033[36m".$message."\033[0m";}
function blanc(string $message){return "\033[37m".$message."\033[0m";}

function grisClair(string $message){return "\033[90m".$message."\033[0m";}
function rougeVif(string $message){return "\033[91m".$message."\033[0m";}
function vertVif(string $message){return "\033[92m".$message."\033[0m";}
function jauneVif(string $message){return "\033[93m".$message."\033[0m";}
function bleuVif(string $message){return "\033[94m".$message."\033[0m";}
function violetVif(string $message){return "\033[95m".$message."\033[0m";}
function cyanVif(string $message){return "\033[96m".$message."\033[0m";}
function blancVif(string $message){return "\033[97m".$message."\033[0m";}

function orange(string $message) {return "\033[38;2;255;165;0m" . $message . "\033[0m";}
