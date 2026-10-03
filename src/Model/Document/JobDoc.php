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

	/** Current state: pending | processing | completed | failed */
	public string $status = 'pending';

	/** Error message from the last failed attempt, if any. */
	public string $error;

	/** Number of execution attempts so far. */
	public int $attempts = 0;

	/** Maximum number of attempts before the job is marked failed. */
	public int $max_attempts = 3;

	public ?DateTime $started_at = null;
	public ?DateTime $completed_at = null;

}