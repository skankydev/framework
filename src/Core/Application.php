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

namespace SkankyDev\Core;

use Exception;
use SkankyDev\Config\Config;
use SkankyDev\Core\ExceptionHandler;
use SkankyDev\Exception\NotFoundException;
use SkankyDev\Http\Middleware\MiddlewareManager;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Utilities\Session;


class Application {
	
	
	protected ExceptionHandler $exceptionHandler;
	
	/**
	 * Bootstraps the application: initializes config, sets up the exception handler
	 * and registers a shutdown function to catch fatal errors.
	 */
	public function __construct() {
		Config::initConf();
		// Initialiser le gestionnaire d'exceptions
		$debug = Config::get('debug') ?? false;
		$this->exceptionHandler = new ExceptionHandler($debug);
		
		// Enregistrer le gestionnaire global
		set_exception_handler([$this->exceptionHandler, 'handle']);
		
		// Gérer aussi les erreurs fatales
		register_shutdown_function(function() {
			$error = error_get_last();
			if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
				$exception = new \ErrorException(
					$error['message'],
					0,
					$error['type'],
					$error['file'],
					$error['line']
				);
				$this->exceptionHandler->handle($exception);
			}
		});
	}

	/**
	 * Loads routes, resolves the current route from the request URI,
	 * runs the middleware pipeline and sends the response.
	 */
	public function run(): void {
		try {
			
			include_once APP_FOLDER.DS.'routes'.DS.'routes.php';
			$request = Request::getInstance();
			$current = Router::_findCurrentRoute($request->uri());
			$manager = new MiddlewareManager();
			
			$response = $manager->run($request, $current, function($request) use ($current) {
				$controller = MasterFactory::_make($current->getController());
				return MasterFactory::_call($controller, $current->getAction(), $current->getParams());
			});
			if($response){
				$response->send();
			}
		} catch (NotFoundException $e) {
			// Pas un plantage : méthode/controller/donnée introuvable. En debug
			// la trace reste utile (ExceptionHandler::handle() normal) ; en
			// prod on affiche la vraie 404 (vue normale, cf. notFound()) plutôt
			// que la page d'erreur générique — ExceptionHandler se contente de logger.
			// Le code 404 distingue "vraiment pas trouvé" (route/donnée) d'un
			// vrai bug de code qui réutilise NotFoundException avec un autre
			// code (ex: 500 sur un appel de méthode inconnu en interne) — ce
			// dernier cas doit rester visible comme une vraie erreur en prod.
			if (Config::get('debug') || $e->getCode() !== 404) {
				$this->exceptionHandler->handle($e);
			} else {
				$this->exceptionHandler->log($e);
				notFound()->send();
			}
		} catch (Exception $e) {
			$this->exceptionHandler->handle($e);
		}
	}
}
