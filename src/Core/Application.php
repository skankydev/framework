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
use SkankyDev\Event\EventManager;
use SkankyDev\Http\Event\PageNotFound;
use SkankyDev\Http\Event\ResponseSent;
use SkankyDev\Http\Response;
use Throwable;
use SkankyDev\Exception\NotFoundException;
use SkankyDev\Http\Middleware\MiddlewareManager;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;


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
		
		// Exceptions, warnings/notices and fatal errors all go through the handler
		$this->exceptionHandler->register();
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
				$this->afterResponse($request, $response);
			}
		} catch (NotFoundException $e) {
			if (Config::get('debug') || $e->getCode() !== 404) {
				if ($e->getCode() === 404) {
					EventManager::_emit(new PageNotFound(Request::getInstance()->uri(), $e));
				}
				$this->exceptionHandler->handle($e);
			} else {
				$this->exceptionHandler->log($e);
				notFound([], $e)->send();
			}
		} catch (Exception $e) {
			$this->exceptionHandler->handle($e);
		}
	}

	/**
	 * Hands the response over to the client, then emits ResponseSent.
	 */
	protected function afterResponse(Request $request, Response $response): void {
		$response->finish();
		try {
			EventManager::_emit(new ResponseSent($request, $response));
		} catch (Throwable $e) {
			// The page is gone: an error here can only be logged
			$this->exceptionHandler->log($e);
		}
	}
}
