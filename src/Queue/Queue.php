<?php

namespace SkankyDev\Queue;

use SkankyDev\Model\Document\JobDoc;
use SkankyDev\Model\JobCollection;
use SkankyDev\Utilities\Log;

class Queue {
	
	const PENDING = 'pending';
	const PROCESSING = 'processing';
	const SUCCESS = 'success';
	const FAILED = 'failed';

	/**
	 * Pushes a job onto the queue by wrapping it in a JobDoc and saving to MongoDB.
	 * The job must implement Persistable so it is stored and restored without a constructor.
	 * @param object $job a MasterJob instance with all payload data set
	 */
	public static function push(object $job): void {
		$jobInfo = new  JobDoc([
			'payload' => $job,
			'status' => Queue::PENDING,
			'attempts' => 0,
			'max_attempts' => 3
		]);
		
		JobCollection::_save($jobInfo);
		Log::job(get_class($job), 'queued');
	}
	


	/**
	 * Returns the oldest pending job (FIFO by created_at), or null if the queue is empty.
	 */
	public static function next(): ?object {
		return JobCollection::_findOne(['status' => 'pending'], ['sort' => ['created_at' => 1]]);
	}
}