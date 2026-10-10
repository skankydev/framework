<?php 

namespace SkankyDev\Command;

use DateTime;
use SkankyDev\Command\MasterCommand;
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
	 * Processes a single job: marks it as processing, runs the payload,
	 * then deletes it on success (un job réussi n'a plus besoin de traîner en base —
	 * ça évite aussi que son payload, potentiellement sensible, y reste indéfiniment)
	 * or increments the attempt counter on failure. Sets status to `failed` when
	 * `max_attempts` is reached.
	 * @param object $jobInfo the JobDoc instance pulled from MongoDB
	 */
	private function processJob(object $jobInfo): void {
		try {
			$jobInfo->status = Queue::PROCESSING;
			$jobInfo->started_at  = new DateTime;
			JobCollection::_save($jobInfo);

			$job = $jobInfo->payload;
			$job->run();

			JobCollection::_deleteOne($jobInfo);
			Log::job(get_class($job), 'completed');

		} catch (\Exception $e) {
			Log::error($e);
			$jobInfo->attempts += 1;
			$jobInfo->status = Queue::PENDING;
			$jobInfo->error = $e->getMessage();
			if($jobInfo->attempts >= $jobInfo->max_attempts){
				$jobInfo->status = Queue::FAILED;
			}
			JobCollection::_save($jobInfo);
			
		}
	}
	
}