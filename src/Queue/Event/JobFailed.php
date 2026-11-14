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

namespace SkankyDev\Queue\Event;

use SkankyDev\Event\MasterEvent;
use SkankyDev\Model\Document\JobDoc;
use Throwable;

/**
 * A job failed for the last time (`max_attempts` reached): it stays `failed`
 * in the `jobs` collection and won't be retried. Emitted by the worker.
 */
class JobFailed extends MasterEvent {

	/**
	 * @param object    $job       the job (payload of the JobDoc)
	 * @param JobDoc    $jobDoc    its queue entry (attempts, error...)
	 * @param Throwable $exception what made the last attempt fail
	 */
	public function __construct(
		public readonly object $job,
		public readonly JobDoc $jobDoc,
		public readonly Throwable $exception,
	) {}
}
