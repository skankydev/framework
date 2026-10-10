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


namespace SkankyDev\Http;

use SkankyDev\Config\Config;
use SkankyDev\Exception\RouteNotFoundException;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyDev\Utilities\Traits\StringFacility;

class UrlBuilder
{
	use Singleton, StringFacility;
	
	/**
	 * Builds a URL from a link array. Tries to match a declared route first,
	 * falls back to the convention-based URL. Appends GET params if provided.
	 * @param  array $link     route link (controller, action, namespace, params, get)
	 * @param  bool  $absolut  if true, prepends scheme + host for an absolute URL
	 * @return string          the generated URL
	 */
	public function build(array $link, bool $absolut = false): string {
		$url = '';
		if(isset($link['name'])){
			$route = $this->findRouteByName($link['name']);
			$url = $this->createUrlFromRoute($link,$route);
		}else{
			$link = $this->completLink($link);
			$route = $this->matcheWithRoute($link);
			if($route){
				$url = $this->createUrlFromRoute($link,$route);
			}else{
				$url = $this->createUrlFromDefault($link);
			}
		}
		if(isset($link['get'])){
			$url = $this->addGet($url,$link['get']);
		}
		if($absolut){
			$request = Request::getInstance();
			$url = $request->scheme().'://'.$request->host().$url;
		}
		return $url;
	}

	/** Builds the URL for the current route. Useful for canonical links. */
	public function buildCurrent(bool $absolut = false): string {
		$current = Router::_getCurrentRoute()->getLink();
		return $this->build($current,$absolut);
	}

	/**
	 * Fills in missing link keys (namespace, controller, action) from the current route.
	 */
	public function completLink(array $link): array {
		$current = Router::_getCurrentRoute()->getLink();
		if(!isset($link['namespace'])){
			$link['namespace'] = $current['namespace'];
		}
		if(!isset($link['controller'])){
			$link['controller'] = $current['controller'];
		}
		if(!isset($link['action'])){
			$link['action'] = Config::getDefaultAction();
		}
		return $link;
	}

	/**
	 * Tries to find a declared route matching the given link (controller + action + namespace).
	 * @return \SkankyDev\Http\Routing\Route\Route|null
	 */
	public function matcheWithRoute(array $link): mixed {
		$collection = Router::_getRoutesCollection();
		if(!empty($collection)){
			//find
			foreach ($collection as $route) {
				$test = $route->getLink();
				if( $link['controller']===$test['controller'] && 
					$link['action']===$test['action'] && 
					$link['namespace']===$test['namespace']
				){
					return $route;
				}
			}
		}
		return null;
	}

	/**
	 * Finds a declared route by its name.
	 * @throws RouteNotFoundException if no route was declared with this name
	 */
	public function findRouteByName(string $name): mixed {
		$route = Router::_findByName($name);
		if(!$route){
			throw new RouteNotFoundException("No route declared with the name \"{$name}\"", 404);
		}
		return $route;
	}

	/**
	 * Builds a URL by filling in the route schema with the provided params.
	 * Named params (`:slug`) are matched by key first, then by position.
	 */
	public function createUrlFromRoute(array $link, mixed $route): string {
		$shema = $route->getShema();
		$tmp = trim($shema,'/');
		$tmp = explode('/', $tmp);
		$k = 0;
		$url = '';
		foreach ($tmp as $key => $value) {
			if(substr($value,0,1)===":"){
				$v = substr($value,1);
				if(array_key_exists($v,$link['params'])){
					$url .= $link['params'][$v].'/';
				}else{
					$url .= $link['params'][$k].'/';
				}
				$k++;
			}else{
				$url .= $value.'/';
			}
		}
		$url = trim($url,'/');
		return '/'.$url;
	}

	/**
	 * Builds a convention-based URL: /namespace/controller/action/param1/param2.
	 * Omits namespace if it is the default, omits action if it is the default and there are no params.
	 * The URL is absolute and CurrentRoute::initFromUri() resolves an unprefixed URI to the
	 * default namespace, so the prefix may only be dropped for the default namespace, never
	 * for the current one (otherwise /admin/post would link to /post/create → App).
	 */
	public function createUrlFromDefault(array $link): string {
		$url = '';
		if($link['namespace']!==Config::getDefaultNamespace()){
			$url .= '/'.$this->toDash($link['namespace']);
		}
		$url .= '/'.$this->toDash($link['controller']);
		if($link['action'] !== Config::getDefaultAction() || isset($link['params'])&&!empty($link['params'])){
			$url .= '/'.$this->toDash($link['action']);
		}

		if(isset($link['params'])&&!empty($link['params'])){
			foreach ($link['params'] as $key => $params){
				$url .= '/'.$params;
			}
		}
		return $url;
	}

	/**
	 * Appends a GET query string to a URL, URL-encoding all keys and values.
	 */
	public function addGet(string $url, array $get): string {
		$url .= '?';
		foreach ($get as $key => $value) {
			$url .= urlencode($key).'='.urlencode($value).'&';
		}
		$url = trim($url,'&');
		return $url;
	}
}