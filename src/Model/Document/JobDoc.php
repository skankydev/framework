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

namespace SkankyDev\Model\Document;

use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Model\Document\Traits\TimedTrait;
use SkankyDev\Model\Enum\JobStatus;
use DateTime;

/**
 * Represents a queued job stored in MongoDB.
 * Both JobDoc and its payload implement Persistable, so MongoDB reconstructs
 * the full object graph (including the nested job) without calling constructors.
 */
class JobDoc extends MasterDocument {

	use TimedTrait;

	/** The job instance to execute — also a Persistable, hydrated recursively. */
	public object $payload;

	/** Current state. A successful job is deleted, so there is no "success" state. */
	public JobStatus $status = JobStatus::PENDING;

	/** Error message from the last failed attempt, if any. */
	public string $error;

	/** Number of execution attempts so far (incremented when a worker claims the job). */
	public int $attempts = 0;

	/** Maximum number of attempts before the job is marked failed. */
	public int $max_attempts = 3;

	/** Lease duration in seconds: past it, a job stuck in processing can be claimed again. */
	public int $timeout = 600;

	public ?DateTime $started_at = null;

	/** End of the current lease (started_at + timeout), computed by Mongo when claiming. */
	public ?DateTime $lease_until = null;

}