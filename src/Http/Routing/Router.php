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

namespace SkankyDev\Http\Routing;



use SkankyDev\Http\Routing\Route\CurrentRoute;
use SkankyDev\Http\Routing\Route\Route;
use SkankyDev\Utilities\Traits\Singleton;


class Router
{
	use Singleton;

	//private $request;
	private $routesCollection;
	private $routes;
	private $uri;
	private $current = false;

	public function __construct(){
		$this->routes = [];
	}

	/**
	 * Add a new route in route Collection
	 * @param string $schema the route shema ex /article/:slug
	 * @param array  $link   ['controller'=>'','action'=>'','params'=>['name'=>'value','name'=>'value'...]]
	 * @param array  $rules  the rules for match with uri ex ['slug'=>'[a-zA-Z0-9-]*']
	 */
	/**
	 * Registers a named route in the collection.
	 * @param string $schema the route pattern e.g. `/article/:slug`
	 * @param array  $link   target: controller, action, namespace
	 * @param array  $rules  regex rules for pattern segments e.g. `['slug' => '[a-z0-9-]+']`
	 */
	public function add(string $schema, array $link, array $rules = []): Route {
		$route = new Route($schema,$link,$rules);
		$this->routesCollection[] = $route;
		return $route;
	}

	/**
	 * get the routes collection
	 * @return arra a array with all routes
	 */
	/** Returns the full declared route collection. */
	public function getRoutesCollection(): ?array {
		return $this->routesCollection;
	}

	/**
	 * get the current route
	 * @return SkankyDev\Routing\Route\CurrenteRoute the currente route object
	 */
	/** Returns the current resolved route. */
	public function getCurrentRoute(): CurrentRoute|false {
		return $this->current;
	}

	/**
	 * find the current route 
	 * @param  string $uri the uri form user request 
	 * @return SkankyDev\Routing\Route\CurrenteRoute   the currente route
	 */
	/**
	 * Resolves the current route from a URI.
	 * Tries declared routes first, falls back to convention-based parsing.
	 * @param  string $uri the request URI path
	 */
	public function findCurrentRoute(string $uri): CurrentRoute {
		$tmp = explode('?', $uri);
		$uri = $tmp[0];
		$route = $this->matchRouteUri($uri);
		$this->current = new CurrentRoute($uri,$route);
		return $this->current;
	}

	/**
	 * try to matche $uri with a route in Collection
	 * @param  string $uri                         the uri form user request 
	 * @return SkankyDev\Routing\Route\Route|null  if a route matche return route or null
	 */
	/**
	 * Iterates the route collection and returns the first route whose regex matches the URI.
	 * @return Route|null null if no declared route matches
	 */
	public function matchRouteUri(string $uri): ?Route {
		if(!empty($this->routesCollection)){
			foreach ($this->routesCollection as $route) {
				$regex = $route->getMatcheRules();
				if(preg_match($regex,$uri)){
					return $route;
				}
			}
		}
		return null;
	}

	/**
	 * Finds a declared route by its name.
	 * @return Route|null null if no route was declared with this name
	 */
	public function findByName(string $name): ?Route {
		if(!empty($this->routesCollection)){
			foreach ($this->routesCollection as $route) {
				if($route->getName() === $name){
					return $route;
				}
			}
		}
		return null;
	}
}