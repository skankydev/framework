<?php

namespace SkankyDev\Queue\Job;

use SkankyDev\Config\Config;
use SkankyDev\Model\Document\EmbeddedDocument;

/**
 * Base class for all queue jobs.
 * Extends EmbeddedDocument (Persistable) so the job and its properties are stored
 * in MongoDB and reconstructed without going through the constructor on retrieval.
 * Concrete jobs set their payload as typed properties in their constructor,
 * then implement run() with the actual business logic.
 */
abstract class MasterJob extends EmbeddedDocument {

	/** Executes the job. Called by QueueWork when the job is processed. */
	abstract public function run(): void;

	/**
	 * Lease duration in seconds (config `queue.timeout` by default): past it, a job still
	 * in processing is considered abandoned and can be claimed again by another worker.
	 * Override it for a job that legitimately runs longer, otherwise it would run twice.
	 */
	public function timeout(): int {
		return Config::get('queue.timeout') ?? 600;
	}

	/**
	 * Returns all job properties as an array.
	 * Useful for debugging or logging the job payload.
	 */
	public function getPayload(): array {
		return get_object_vars($this);
	}

}
