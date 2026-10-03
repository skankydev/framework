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


namespace SkankyDev\Database;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Manager;
use SkankyDev\Config\Config;
use SkankyDev\Utilities\Traits\Singleton;

class MongoClient {

	
	use Singleton;

	private $manager;
	private $client;
	private $dbName;

	/**
	 * Builds the MongoDB URI from config and initializes the Manager and Client.
	 * Credentials are included in the URI only if a username is set.
	 */
	public function __construct(){
		$dbConnect = Config::getDbConf('MongoDB');
		$uri = 'mongodb://';
		if(!empty($dbConnect['username'])){
			$uri .= $dbConnect['username'].':'.$dbConnect['password'].'@';
		}
		$uri .= $dbConnect['host'].':'.$dbConnect['port'];

		$this->manager = new Manager($uri);
		$this->client = new Client($uri);
		$this->dbName = $dbConnect['database'];
	}

	/**
	 * Returns the default database name from config.
	 */
	public function getDbName(): string {
		return $this->dbName;
	}

	/**
	 * Returns a MongoDB Database instance.
	 * Defaults to the configured database if no name is provided.
	 * @param string $name database name, defaults to configured db
	 */
	public function getDatabase(string $name = ''): Database {
		if (empty($name)) {
			$name = $this->dbName;
		}
		return $this->client->selectDatabase($name);
	}

	/**
	 * Returns a MongoDB Collection instance from the default database.
	 * @param string $name collection name
	 */
	public function getCollection(string $name): Collection {
		return $this->client->selectCollection($this->dbName, $name);
	}

	/**
	 * Returns the raw MongoDB Driver Manager instance.
	 */
	public function getManager(): Manager {
		return $this->manager;
	}

	/**
	 * Creates a MongoDB collection with options (e.g. capped, validator).
	 * @param string $name   collection name
	 * @param array  $option MongoDB createCollection options
	 */
	public function createCollection(string $name, array $option): mixed {
		$database = new Database($this->manager, $this->dbName);
		return $database->createCollection($name, $option);
	}

	/**
	 * Creates one or more indexes on a collection.
	 * @param string $name  collection name
	 * @param array  $index index specifications as expected by MongoDB createIndexes
	 */
	public function createIndex(string $name, array $index): mixed {
		$collection = $this->getCollection($name);
		return $collection->createIndexes($index);
	}
}
