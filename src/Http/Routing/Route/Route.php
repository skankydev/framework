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


namespace SkankyDev\Http\Routing\Route;

use SkankyDev\Config\Config;

/**
 * 	
 */
class Route 
{

	/**
     * Valid HTTP methods.
     *
     * @var array
     */
    const VALID_METHODS = ['GET', 'PUT', 'POST', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

	private $shema;
	private $link;
	private $rules;
	private $regex = false;
	private $middlewares = [];
	private $name = null;


	/**
	 * create a new route
	 * @param string $schema the route shema ex /article/:slug
	 * @param array  $link   ['controller'=>'','action'=>'','params'=>['name'=>'value','name'=>'value'...]]
	 * @param array  $rules  the rules for match with uri ex ['slug'=>'[a-zA-Z0-9-]*']
	 */
	/**
	 * @param string $shema  route pattern e.g. `/article/:slug`
	 * @param array  $link   target: controller, action, namespace
	 * @param array  $rules  regex rules for named segments e.g. `['slug' => '[a-z0-9-]+']`
	 */
	public function __construct(string $shema, array $link, array $rules = []){
		$this->shema = $shema;
		$this->link = $link;
		$this->initLink();

		$this->rules = $rules;

	}

	/**
	 * init the link with the default value
	 */
	/** Fills in default action and namespace if not provided in the link array. */
	private function initLink(): void {
		if(!isset($this->link['action'])){
			$this->link['action'] = Config::getDefaultAction();
		}
		if(!isset($this->link['namespace'])){
			$this->link['namespace'] = Config::getDefaultNamespace();	
		}		
	}

	
	

	/**
	 * get the link array
	 * @return array the link
	 */
	public function getLink(){
		return $this->link;
	}

	/**
	 * get the rules
	 * @return array the rules
	 */
	public function getRules(){
		return $this->rules;
	}

	/**
	 * get the shema
	 * @return string the shema
	 */
	public function getShema(){
		return $this->shema;
	}

	/**
	 * create te regex for matchin route
	 */
	/**
	 * Compiles the route schema into a regex pattern.
	 * Named segments (`:slug`) are replaced with their corresponding rule pattern.
	 */
	private function makeRegex(): void {
		$tmp = str_replace('/','\/',$this->shema);
		$tmp = preg_replace_callback('/:[a-z0-9]*/',[$this,'pregCallback'],$tmp);
		$this->regex = '/^'.$tmp.'$/';
	}


	/**
	 *	/!\ this methode MUST NOT be called /!\
	 * 
	 * replace the param expected by the rules
	 * @param  string $params the string form preg_replace_callback ex: :slug
	 * @return string         the rulse 
	 */
	private function pregCallback($params){
		$key = $params[0];
		$key = substr($key,1);
		return $this->rules[$key];
	}

	/**
	 * get the regex for matche with uri
	 * @return string the regex
	 */
	/**
	 * Returns the compiled regex for this route. Compiles it on first call.
	 */
	public function getMatcheRules(): string {
		if(!$this->regex){
			$this->makeRegex();
		}
		return $this->regex;
	}

	/**
	 * Sets the middlewares run for this route.
	 * Plain values (`['Auth', 'Session']`) carry no constructor args. A string
	 * key paired with an array value (`['RateLimit' => ['login', 5, TIME_MINUTE * 15]]`)
	 * passes that array as constructor args, same as `#[Middleware(...)]` on a controller.
	 */
	public function setMiddlewares(array $middlewares): static {
		$this->middlewares = $middlewares;
		return $this;
	}

	public function getMiddlewares(){
		return $this->middlewares;
	}

	/** Sets the route's name, used to build its URL via UrlBuilder without matching controller/action/namespace. */
	public function setName(string $name): static {
		$this->name = $name;
		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}
}