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
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use SkankyDev\Exception\ClassNotFoundException;
use SkankyDev\Exception\UnknownMethodException;
use SkankyDev\Exception\ModelNotFoundException;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyDev\Model\Document\MasterDocument;

class MasterFactory {

	use Singleton;

	/**
	 * Calls a method on an existing object, resolving its dependencies automatically.
	 * @param object $object     the target object
	 * @param string $method     the method name to call
	 * @param array  $parameters optional raw parameters to resolve from
	 * @return mixed             the return value of the called method
	 * @throws UnknownMethodException if the method does not exist
	 */
	public function call(object $object, string $method, array $parameters = []): mixed {

		if (!method_exists($object, $method)) {
			throw new UnknownMethodException(
				'Unknown method : ' . $method . ' in Class : ' . get_class($object),
				404
			);
		}

		$reflector = new ReflectionMethod($object, $method);

		$dependencies = $this->resolveDependencies(
			$reflector->getParameters(),
			$parameters
		);

		return $reflector->invokeArgs($object, $dependencies);
	}


	/**
	 * Instantiates a class by resolving its constructor dependencies automatically.
	 * Handles Singleton classes, classes without constructors, and full DI resolution.
	 * @param string $className  fully qualified class name
	 * @param array  $parameters optional raw parameters to resolve from
	 * @return object            the instantiated object
	 * @throws ClassNotFoundException if the class does not exist or is not instantiable
	 */
	public function make(string $className, array $parameters = []): object {

		try {
			$reflector = new ReflectionClass($className);
		} catch (\ReflectionException $e) {
			throw new ClassNotFoundException("La classe {$className} n'existe pas", 404);
		}

		if (!$reflector->isInstantiable()) {
			throw new ClassNotFoundException("La classe {$className} n'est pas instanciable",404);
		}

		if (in_array(Singleton::class, $reflector->getTraitNames())) {
			return $className::getInstance();
		}
		
		$constructor = $reflector->getConstructor();

		if ($constructor === null) {
			return new $className();
		}

		$dependencies = $this->resolveDependencies(
			$constructor->getParameters(),
			$parameters
		);
		
		return $reflector->newInstanceArgs($dependencies);
	}

	/**
	 * Resolves an array of ReflectionParameters into concrete values.
	 * Resolution order for each parameter:
	 * - MasterDocument subclass → fetched from DB using route param ID
	 * - Named key match in $value → used directly
	 * - Builtin/no type → uses default value or positional value from $value
	 * - Class type → recursively resolved via make()
	 * @param  \ReflectionParameter[] $parameters constructor or method parameters
	 * @param  array                  $value      raw values to resolve from (route params, etc.)
	 * @return array                  resolved dependency instances
	 * @throws ClassNotFoundException if a required dependency cannot be resolved
	 */
	protected function resolveDependencies(array $parameters, array $value = []): array {
		$dependencies = [];
		$paramKey = 0;

		foreach ($parameters as $parameter) {
			$name = $parameter->getName();
			$type = $parameter->getType();
			$className = $type->getName();

			if (is_subclass_of($className, MasterDocument::class)) {
				$id = false;
				if(isset($value[$name])){
					$id = $value[$name];

				}else if(isset($value[$paramKey])){
					$id = $value[$paramKey];
					$paramKey++;
				}

				if($id){
					$model = $className::find($id);
					if (!$model) {
						throw new ModelNotFoundException("Document {$className} avec ID {$id} introuvable",404);
					}
					$dependencies[] = $model;
                	continue;
				}
			}

			if (array_key_exists($name, $value)) {
				$dependencies[] = $value[$name];
				continue;
			}

			// Si pas de type ou type primitif
			if ($type === null || $type->isBuiltin()) {
				// Un argument explicitement fourni gagne sur la valeur par défaut.
				if (array_key_exists($paramKey, $value)) {
					$dependencies[] = $value[$paramKey];
					$paramKey++;
				} else if ($parameter->isDefaultValueAvailable()) {
					$dependencies[] = $parameter->getDefaultValue();
				} else {
					throw new ClassNotFoundException("Impossible de résoudre le paramètre {$name}",500);
				}
				continue;
			}


			// Résoudre la dépendance de classe
			$className = $type->getName();
			$dependencies[] = $this->make($className);
		}

		return $dependencies;
	}
}