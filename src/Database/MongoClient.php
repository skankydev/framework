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
use MongoDB\Driver\Session;
use SkankyDev\Config\Config;
use SkankyDev\Utilities\Traits\Singleton;

use function MongoDB\with_transaction;

class MongoClient {


	use Singleton;

	private $manager;
	private $client;
	private $dbName;

	/** Session of the transaction in progress, null outside a transaction. */
	private ?Session $session = null;

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

	/**
	 * Returns the session of the transaction in progress, or null outside a transaction.
	 * MasterCollection adds it to every operation (see MasterCollection::withSession()).
	 */
	public function getSession(): ?Session {
		return $this->session;
	}

	/**
	 * Runs $callback in a transaction: every MasterCollection operation inside it
	 * is committed together, or not at all if an exception comes out.
	 * Only when `db.MongoDB.transaction` is true (it needs a replica set): otherwise,
	 * or when already inside a transaction (Mongo doesn't nest them), $callback just runs.
	 * with_transaction() retries $callback on transient errors: it may run more than once.
	 * @return mixed what $callback returns
	 */
	public function transaction(callable $callback): mixed {
		if (!Config::get('db.MongoDB.transaction') || $this->session) {
			return $callback();
		}

		$this->session = $this->client->startSession();
		try {
			$result = null;
			with_transaction($this->session, function () use ($callback, &$result) {
				$result = $callback();
			});
			return $result;
		} finally {
			$this->session->endSession();
			$this->session = null;
		}
	}
}
