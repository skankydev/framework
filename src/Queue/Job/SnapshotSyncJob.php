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

namespace SkankyDev\Queue\Job;

use MongoDB\BSON\ObjectId;

/**
 * Resyncs the snapshots (EmbeddedSnapshot) of a source document in one target.
 *
 * The job re-reads the source when it runs; it does not carry the snapshot:
 * - source found → snapshot rebuilt and rewritten everywhere it is embedded;
 * - source gone → `onDelete` is applied (keep: nothing, mark: `_deleted`, unset: field removed).
 * It is therefore idempotent and order-insensitive: the last run always writes the current state.
 *
 * Dispatched by MasterCollection: `run()` directly when sync, `Queue::push()` otherwise.
 */
class SnapshotSyncJob extends MasterJob {

	/** Source Collection class (e.g. UserCollection::class). */
	public string $source = '';

	/** _id of the source document. */
	public ?ObjectId $source_id = null;

	/** Target config entry, defaults applied (see MasterCollection::snapshotTargets()). */
	public array $target = [];

	public function run(): void {
		$target = $this->target;
		$field  = $target['field'];
		$filter = [$field . '._id' => $this->source_id];
		$targetCollection = $target['collection']::getInstance();

		$source = $this->source::getInstance()->findById((string) $this->source_id);

		if ($source === null) {
			match ($target['onDelete']) {
				'mark'  => $targetCollection->updateMany($filter, ['$set' => [$field . '._deleted' => true]]),
				'unset' => $targetCollection->updateMany($filter, ['$unset' => [$field => '']]),
				default => null,
			};
			return;
		}

		$targetCollection->updateMany($filter, ['$set' => [$field => new $target['class']($source)]]);
	}

}
