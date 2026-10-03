<?php

namespace SkankyDev\Queue\Job;

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
	 * Returns all job properties as an array.
	 * Useful for debugging or logging the job payload.
	 */
	public function getPayload(): array {
		return get_object_vars($this);
	}

}
