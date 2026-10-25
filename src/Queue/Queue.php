<?php

namespace SkankyDev\Queue;

use SkankyDev\Model\Document\JobDoc;
use SkankyDev\Model\Enum\JobStatus;
use SkankyDev\Model\JobCollection;
use SkankyDev\Utilities\Log;

class Queue {

	/**
	 * Pushes a job onto the queue by wrapping it in a JobDoc and saving to MongoDB.
	 * The job must implement Persistable so it is stored and restored without a constructor.
	 * @param object $job a MasterJob instance with all payload data set
	 */
	public static function push(object $job): void {
		$jobInfo = new  JobDoc([
			'payload' => $job,
			'status' => JobStatus::PENDING,
			'attempts' => 0,
			'max_attempts' => 3,
			'timeout' => $job->timeout(),
		]);

		JobCollection::_save($jobInfo);
		Log::job(get_class($job), 'queued');
	}



	/**
	 * Claims the next job to run (see JobCollection::claimNext()), or null if the queue is empty.
	 * The returned job is already marked processing.
	 */
	public static function next(): ?JobDoc {
		return JobCollection::_claimNext();
	}
}
