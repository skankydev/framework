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

namespace SkankyDev\Http\Middleware;

use ReflectionClass;
use ReflectionMethod;
use SkankyDev\Config\Config;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Exception\MiddlewareNotFoundException;
use SkankyDev\Http\Middleware\Attribute\Middleware;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Route\CurrentRoute;


class MiddlewareManager {

	private array $default = [];
	private array $asso = [];

	/**
	 * Loads default middlewares and the middleware alias map from config.
	 */
	public function __construct(){
		$this->default = Config::get('middlewares');
		$this->asso  = Config::get('class.middlewares');
	}

	/**
	 * Builds and executes the middleware pipeline for the current request.
	 * Merges default middlewares with any route-specific ones, deduplicates,
	 * then runs through the pipeline ending with the controller callback.
	 * @return mixed the response returned by the pipeline
	 */
	public function run(Request $request, CurrentRoute $route, callable $callback): mixed {
		$specs = array_merge(
			$this->normalize($this->default),
			$this->attributeMiddlewares($route->getController(), $route->getAction()),
			$this->normalize($route->getMiddlewares()),
		);
		$specs = $this->dedupe($specs);
		$pipeline = $this->getPipeline($specs, $callback);
		return $pipeline($request);
	}

	/**
	 * Turns a middleware list (globals, route) into specs.
	 * Plain values (`['Auth', 'Session']`) carry no args. A string key paired
	 * with an array value (`['RateLimit' => ['login', 5, TIME_MINUTE * 15]]`)
	 * passes that array as constructor args, same as `#[Middleware(...)]`.
	 * @param array<int|string, mixed> $names
	 * @return array<int, array{class: string, args: array}>
	 */
	private function normalize(array $names): array {
		$specs = [];
		foreach ($names as $key => $value) {
			if (is_int($key)) {
				$specs[] = ['class' => $value, 'args' => []];
			} else {
				$specs[] = ['class' => $key, 'args' => (array) $value];
			}
		}
		return $specs;
	}

	/**
	 * Removes duplicate specs (same class AND same args).
	 * @param array<int, array{class: string, args: array}> $specs
	 */
	private function dedupe(array $specs): array {
		$seen = [];
		$unique = [];
		foreach ($specs as $spec) {
			$key = $spec['class'] . '|' . serialize($spec['args']);
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$unique[] = $spec;
			}
		}
		return $unique;
	}

	/**
	 * Collects the middlewares declared via #[Middleware] attributes on the
	 * controller class and, more specifically, on the action method.
	 * Class-level middlewares come first (they guard the whole controller),
	 * then the action-specific ones. Each attribute yields a spec carrying the
	 * middleware class and the arguments to pass to its constructor.
	 * @return array<int, array{class: string, args: array}>
	 */
	public function attributeMiddlewares(string $controller, string $action): array {
		if (!class_exists($controller)) {
			return [];
		}

		$reflection = new ReflectionClass($controller);
		$targets = [$reflection];
		if ($reflection->hasMethod($action)) {
			$targets[] = $reflection->getMethod($action);
		}

		$specs = [];
		foreach ($targets as $target) {
			/** @var ReflectionClass|ReflectionMethod $target */
			foreach ($target->getAttributes(Middleware::class) as $attribute) {
				$middleware = $attribute->newInstance();
				$specs[] = ['class' => $middleware->middleware, 'args' => $middleware->args];
			}
		}

		return $specs;
	}

	/**
	 * Wraps middleware specs around the destination callable in reverse order,
	 * producing a single callable that represents the full pipeline.
	 * Resolves the class via the alias map, then instantiates it with its args.
	 * Existence is checked eagerly (fail-fast) so an unknown middleware fails
	 * with a clear message before any part of the pipeline runs.
	 * @param array<int, array{class: string, args: array}> $specs
	 * @throws MiddlewareNotFoundException if a middleware cannot be resolved to a class
	 */
	protected function getPipeline(array $specs, callable $destination): callable {
		$pipeline = $destination;
		foreach (array_reverse($specs) as $spec) {
			$name      = $spec['class'];
			$className = $this->asso[$name] ?? $name;
			if (!class_exists($className)) {
				throw new MiddlewareNotFoundException(
					"Middleware introuvable : « {$name} »"
					. ($className !== $name ? " (résolu en {$className})" : '')
					. ". Vérifie le nom de classe, son import, ou son alias dans class.middlewares."
				);
			}
			$args = $spec['args'];
			$pipeline = function($request) use ($className, $args, $pipeline) {
				$middleware = MasterFactory::_make($className, $args);
				return $middleware->handle($request, $pipeline);
			};
		}
		return $pipeline;
	}

}