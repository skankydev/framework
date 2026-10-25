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

namespace SkankyDev\Command;

use MongoDB\Database;
use SkankyDev\Command\MasterCommand;
use SkankyDev\Config\Config;
use SkankyDev\Database\MongoClient;

/**
 * Initializes a single-node replica set, needed by transactions (db.MongoDB.transaction).
 * Can't edit mongod's config nor restart it (admin rights, path depends on the install):
 * it checks where things stand and explains what's left to do by hand. Safe to run again.
 */
class DbReplica extends MasterCommand {

	static protected string $signature = 'db-replica';
	static protected string $help = 'skankydev.cli.db_replica.help';

	public function run(array $arg = []): void {
		$admin = MongoClient::_getDatabase('admin');
		$hello = $this->command($admin, ['hello' => 1]);

		if (isset($hello['setName'])) {
			$this->success(__('skankydev.cli.db_replica.already', ['name' => $hello['setName']]));
			$this->info(__('skankydev.cli.db_replica.transaction_hint'));
			return;
		}

		// Options mongod was started with (needs the clusterMonitor role when auth is on)
		$options = $this->cmdLineOpts($admin);

		if (empty($hello['isreplicaset'])) {
			$this->notEnabled($options['config'] ?? null);
			return;
		}

		// replSetName from a config file, replSet from the --replSet flag
		$name = $options['replication']['replSetName'] ?? $options['replication']['replSet'] ?? null;
		$db   = Config::getDbConf('MongoDB');
		$host = $db['host'] . ':' . $db['port'];

		$this->info(__('skankydev.cli.db_replica.initiating', ['name' => $name ?? '?', 'host' => $host]));
		// Without the name, Mongo builds the config itself (its hostname as member)
		$admin->command(['replSetInitiate' => $name ? ['_id' => $name, 'members' => [['_id' => 0, 'host' => $host]]] : new \stdClass]);

		if (!$this->waitForPrimary($admin)) {
			$this->warning(__('skankydev.cli.db_replica.timeout'));
			return;
		}
		$this->success(__('skankydev.cli.db_replica.initiated'));
		$this->info(__('skankydev.cli.db_replica.transaction_hint'));
	}

	/** mongod isn't started as a replica set member: shows what to add to its config. */
	private function notEnabled(?string $configFile): void {
		$this->error(__('skankydev.cli.db_replica.not_enabled'));
		$this->text($configFile
			? __('skankydev.cli.db_replica.config_file', ['file' => $configFile])
			: __('skankydev.cli.db_replica.config_unknown'));
		$this->text('');
		$this->text('  replication:');
		$this->text('    replSetName: rs0');
		$this->text('');
		$this->text(__('skankydev.cli.db_replica.restart'));
	}

	/** Parsed startup options of mongod, or [] when they can't be read (missing rights). */
	private function cmdLineOpts(Database $admin): array {
		try {
			return $this->command($admin, ['getCmdLineOpts' => 1])['parsed'] ?? [];
		} catch (\MongoDB\Driver\Exception\Exception $e) {
			return [];
		}
	}

	/** Waits (up to ~30 s) for the node to be elected primary after replSetInitiate. */
	private function waitForPrimary(Database $admin): bool {
		for ($i = 0; $i < 30; $i++) {
			if (!empty($this->command($admin, ['hello' => 1])['isWritablePrimary'])) {
				return true;
			}
			sleep(1);
		}
		return false;
	}

	/** Runs an admin command and returns its result as a plain array. */
	private function command(Database $admin, array $command): array {
		$result = $admin->command($command, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
		return $result->toArray()[0];
	}
}
