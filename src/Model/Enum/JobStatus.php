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

namespace SkankyDev\Model\Enum;

/**
 * Status of a queued job (JobDoc).
 * No `success` case: a successful job is deleted, not kept.
 * In raw Mongo queries (filters, findOneAndUpdate), use `->value`.
 */
enum JobStatus: string {
	case PENDING    = 'pending';
	case PROCESSING = 'processing';
	case FAILED     = 'failed';
}
