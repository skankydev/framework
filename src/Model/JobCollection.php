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

namespace SkankyDev\Model;

use MongoDB\Operation\FindOneAndUpdate;
use SkankyDev\Config\Config;
use SkankyDev\Model\Document\JobDoc;
use SkankyDev\Model\Enum\JobStatus;
use SkankyDev\Model\MasterCollection;
use SkankyDev\Utilities\Traits\Singleton;

class JobCollection extends MasterCollection {

	use Singleton;

	protected string $collectionName = 'jobs';
	protected string $documentClass = JobDoc::class;

	/** Index used by claimNext(): pending jobs, oldest first. */
	public function getIndexes(): array {
		return [['key' => ['status' => 1, 'created_at' => 1]]];
	}

	/**
	 * Atomically claims the next job to run (FIFO by created_at), or returns null.
	 * Takes a pending job, or a processing job whose lease has expired (its worker died).
	 * Read and switch to processing happen in a single findOneAndUpdate: two workers
	 * can never claim the same job.
	 * The update is a pipeline so Mongo computes the dates itself ($$NOW, $dateAdd):
	 * every worker shares the server's clock, and the lease uses the job's own timeout.
	 * `attempts` is incremented here, not on failure: a job that kills its worker
	 * still ends up reaching max_attempts.
	 */
	public function claimNext(): ?JobDoc {
		$leaseExpired = ['status' => JobStatus::PROCESSING->value, '$expr' => ['$lt' => ['$lease_until', '$$NOW']]];

		// Expired lease and no attempt left: the worker died on the last try
		$this->collection->updateMany(
			['$and' => [$leaseExpired, ['$expr' => ['$gte' => ['$attempts', '$max_attempts']]]]],
			[['$set' => ['status' => JobStatus::FAILED->value, 'error' => 'Lease expired', 'updated_at' => '$$NOW']]],
			$this->withSession()
		);

		$timeout = ['$ifNull' => ['$timeout', Config::get('queue.timeout') ?? 600]];
		return $this->collection->findOneAndUpdate(
			['$or' => [['status' => JobStatus::PENDING->value], $leaseExpired]],
			[['$set' => [
				'status'      => JobStatus::PROCESSING->value,
				'attempts'    => ['$add' => ['$attempts', 1]],
				'started_at'  => '$$NOW',
				'updated_at'  => '$$NOW',
				'lease_until' => ['$dateAdd' => ['startDate' => '$$NOW', 'unit' => 'second', 'amount' => $timeout]],
			]]],
			$this->withSession(['sort' => ['created_at' => 1], 'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER])
		);
	}

}
