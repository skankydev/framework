<?php 

namespace SkankyDev\Command;

use SkankyDev\Command\MasterCommand;
use SkankyDev\Model\Document\JobDoc;
use SkankyDev\Model\Enum\JobStatus;
use SkankyDev\Model\JobCollection;
use SkankyDev\Queue\Queue;
use SkankyDev\Utilities\Log;

class QueueWork extends MasterCommand {


	static protected string $signature = 'queue-worker';
	static protected string $help = 'skankydev.cli.queue_worker.help';

	/**
	 * Starts the queue worker loop.
	 * Polls for the next pending job every second and processes it.
	 * Runs indefinitely until the process is killed.
	 * @param array $arg unused
	 */
	public function run(array $arg = []): void {
		$this->info(__('skankydev.cli.queue_worker.started'));

		while (true) {
			$jobInfo = Queue::next();

			if (!$jobInfo) {
				sleep(1);
				continue;
			}

			$this->processJob($jobInfo);
		}
	}

	/**
	 * Processes a single job, already claimed (processing, attempts counted) by Queue::next().
	 * Runs the payload, then deletes it on success (un job réussi n'a plus besoin de traîner en base —
	 * ça évite aussi que son payload, potentiellement sensible, y reste indéfiniment).
	 * On failure, puts it back to `pending`, or `failed` when `max_attempts` is reached.
	 * Catches \Throwable: an \Error (TypeError…) must not kill the worker and leave the job stuck.
	 * @param JobDoc $jobInfo the JobDoc instance claimed from MongoDB
	 */
	private function processJob(JobDoc $jobInfo): void {
		try {
			$job = $jobInfo->payload;
			$job->run();

			JobCollection::_deleteOne($jobInfo);
			Log::job(get_class($job), 'completed');

		} catch (\Throwable $e) {
			Log::error($e);
			$jobInfo->error = $e->getMessage();
			$jobInfo->status = $jobInfo->attempts >= $jobInfo->max_attempts ? JobStatus::FAILED : JobStatus::PENDING;
			JobCollection::_save($jobInfo);
		}
	}
	
}