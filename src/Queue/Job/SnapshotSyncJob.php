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
use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Model\MasterCollection;

/**
 * Resyncs the snapshots (EmbeddedSnapshot) of a source document in one target.
 *
 * The job re-reads the source when it runs; it does not carry the snapshot:
 * - source found → snapshot rebuilt and rewritten everywhere it is embedded;
 * - source gone → `onDelete` is applied (keep: nothing, mark: `_deleted`, unset: field removed,
 *   or item pulled out of the array for a `many` target).
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

		if ($target['many'] ?? false) {
			$this->runMany($targetCollection, $field, $filter, $source);
			return;
		}

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

	/**
	 * Same as run() for a `many` target (array of snapshots): only the items carrying
	 * the source _id are touched, through `arrayFilters` (every match, not just the first).
	 * `unset` pulls the item out of the array instead of removing the field.
	 */
	private function runMany(MasterCollection $targetCollection, string $field, array $filter, ?MasterDocument $source): void {
		$item    = $field . '.$[snap]';
		$options = ['arrayFilters' => [['snap._id' => $this->source_id]]];

		if ($source === null) {
			match ($this->target['onDelete']) {
				'mark'  => $targetCollection->updateMany($filter, ['$set' => [$item . '._deleted' => true]], $options),
				'unset' => $targetCollection->updateMany($filter, ['$pull' => [$field => ['_id' => $this->source_id]]]),
				default => null,
			};
			return;
		}

		$targetCollection->updateMany($filter, ['$set' => [$item => new $this->target['class']($source)]], $options);
	}

}
