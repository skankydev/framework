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

namespace SkankyDev\Model;

use SkankyDev\Model\Document\JobDoc;
use SkankyDev\Model\MasterCollection;
use SkankyDev\Utilities\Traits\Singleton;

class JobCollection extends MasterCollection {

	use Singleton;

	protected string $collectionName = 'jobs';
	protected string $documentClass = JobDoc::class;
	
}