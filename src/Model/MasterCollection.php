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

use MongoDB\BSON\ObjectId;
use MongoDB\Collection as MongoCollection;
use SkankyDev\Config\Config;
use SkankyDev\Core\MasterFactory;
use SkankyDev\Database\MongoClient;
use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Queue\Job\SnapshotSyncJob;
use SkankyDev\Queue\Queue;
use SkankyDev\Utilities\Paginator;
use SkankyDev\Utilities\Traits\Singleton;

abstract class MasterCollection {

	use Singleton;
	
	protected MongoCollection $collection;
	protected string $collectionName;
	protected string $documentClass;
	protected array $behaviors = [];
	
	/**
	 * Connects to the MongoDB collection and loads the declared behaviors.
	 */
	public function __construct() {
		$this->collection = MongoClient::getInstance()->getCollection($this->collectionName);
		$this->loadBehaviors();
	}

	/**
	 * Instantiates the behaviors attached to the document class.
	 * Behaviors are declared on the document by using their companion trait
	 * (e.g. `use TimedTrait`), and resolved here by convention:
	 * a trait `...\Model\Document\Traits\FooTrait` maps to the behavior class
	 * `...\Model\Behavior\FooBehavior` (same top namespace, so it works in `App\` too).
	 * This keeps the document as the single source of truth — the trait both declares
	 * the managed properties and signals which behavior should run.
	 */
	protected function loadBehaviors(): void {
		$loadedBehaviors = [];
		foreach ($this->documentTraits($this->documentClass) as $trait) {
			$behaviorClass = str_replace('\\Document\\Traits\\', '\\Behavior\\', $trait);
			$behaviorClass = preg_replace('/Trait$/', 'Behavior', $behaviorClass);
			if ($behaviorClass !== $trait && class_exists($behaviorClass)) {
				$loadedBehaviors[] = MasterFactory::_make($behaviorClass);
			}
		}

		$this->behaviors = $loadedBehaviors;
	}

	/**
	 * Collects every trait used by the document class, including those used by
	 * its parent classes and traits nested inside other traits.
	 * @return string[] fully qualified trait names, keyed by themselves
	 */
	private function documentTraits(string $class): array {
		$traits = [];
		foreach (array_merge([$class], class_parents($class) ?: []) as $current) {
			$traits += class_uses($current) ?: [];
		}

		$stack = $traits;
		while ($stack) {
			foreach (class_uses(array_shift($stack)) ?: [] as $nested) {
				if (!isset($traits[$nested])) {
					$traits[$nested] = $nested;
					$stack[$nested]  = $nested;
				}
			}
		}

		return $traits;
	}
	
	/**
	 * Calls a hook method on every loaded behavior that implements it.
	 * @param string $method   hook name e.g. `beforeInsert`, `afterUpdate`
	 * @param object $document the document being processed
	 */
	protected function callBehaviors(string $method, object $document): void {
		foreach ($this->behaviors as $behavior) {
			if (method_exists($behavior, $method)) {
				$behavior->{$method}($document);
			}
		}
	}
	
	/**
	 * Returns all documents matching the filter.
	 * MongoDB auto-hydrates them into the correct Document class via Persistable.
	 * @param array $filter  MongoDB filter
	 * @param array $options MongoDB options (limit, skip, sort, projection, etc.)
	 */
	public function find(array $filter = [], array $options = []): array {
		$with = $options['with'] ?? [];
		unset($options['with']);

		$cursor = $this->collection->find($filter, $options);
		$documents = iterator_to_array($cursor, false);

		if ($with && $documents) {
			$this->loadRelations($documents, $with);
		}
		return $documents;
	}

	/**
	 * Returns the first document matching the filter, or null if none found.
	 * Also accepts the `with` option (see find()).
	 */
	public function findOne(array $filter = [], array $options = []): ?object {
		$with = $options['with'] ?? [];
		unset($options['with']);

		$result = $this->collection->findOne($filter, $options);

		if ($with && $result) {
			$this->loadRelations([$result], $with);
		}
		return $result;
	}
	
	/**
	 * Returns a document by its string ID, or null if not found or ID is invalid.
	 */
	public function findById(string $id, array $options = []): ?object {
		try {
			$objectId = new ObjectId($id);
			return $this->findOne(['_id' => $objectId], $options);
		} catch (\Exception $e) {
			return null;
		}
	}
	
	/**
	 * Inserts a new document into the collection.
	 * Fires beforeInsert and afterInsert behavior hooks, unless $withBehaviors is false
	 * (e.g. a housekeeping write that shouldn't touch behavior-managed fields like updated_at).
	 * Populates $document->_id with the inserted ObjectId.
	 */
	public function insert(object $document, bool $withBehaviors = true): bool {
		try {
			if ($withBehaviors) {
				$this->callBehaviors('beforeInsert', $document);
			}
			$result = $this->collection->insertOne($document);
			if ($result->getInsertedId()) {
				$document->_id = $result->getInsertedId();
			}
			if ($withBehaviors) {
				$this->callBehaviors('afterInsert', $document);
			}
			if ($document instanceof MasterDocument) {
				$document->syncOriginal();
			}

			return true;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Updates an existing document matched by its _id.
	 * Fires beforeUpdate and afterUpdate behavior hooks, unless $withBehaviors is false
	 * (e.g. a housekeeping write that shouldn't touch behavior-managed fields like updated_at).
	 *
	 * A MasterDocument only writes its changed fields (dirty tracking): two
	 * instances of the same document loaded concurrently no longer overwrite
	 * each other's fields. Nothing changed → no write, no afterUpdate.
	 * afterUpdate behaviors can still read getDirty(): the persisted state
	 * is only resynced after them.
	 * @throws \Exception if the document has no _id
	 */
	public function update(object $document, bool $withBehaviors = true): bool {
		try {
			if (empty($document->_id)) {
				throw new \Exception("Cannot update document without _id");
			}

			if ($withBehaviors) {
				$this->callBehaviors('beforeUpdate', $document);
			}

			$set = $document instanceof MasterDocument ? $document->dirtyData() : $document;
			if (empty($set)) {
				return false;
			}

			$result = $this->collection->updateOne(
				['_id' => $document->_id],
				['$set' => $set]
			);

			if ($withBehaviors) {
				$this->callBehaviors('afterUpdate', $document);
			}
			if ($document instanceof MasterDocument) {
				if ($this->embeddedIn() && $withBehaviors) {
					$this->propagateSnapshots($document->_id, $document->getDirty());
				}
				$document->syncOriginal();
			}

			return $result->getModifiedCount() > 0;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Inserts or updates the document depending on whether _id is set.
	 * @param bool $withBehaviors passé tel quel à insert()/update() — false pour désactiver les behaviors.
	 */
	public function save(object $document, bool $withBehaviors = true): bool {
		if (!empty($document->_id)) {
			return $this->update($document, $withBehaviors);
		} else {
			return $this->insert($document, $withBehaviors);
		}
	}

	/** Sauvegarde sans déclencher les behaviors (ex. `updated_at`) — alias lisible de `save($document, false)`. */
	public function saveSneaky(object $document): bool {
		return $this->save($document, false);
	}
	
	/**
	 * Deletes a document by its string ID.
	 */
	public function deleteById(string $id): bool {
		try {
			$objectId = new ObjectId($id);
			$result = $this->collection->deleteOne([
				'_id' => $objectId
			]);
			if ($result->getDeletedCount() > 0) {
				$this->propagateSnapshots($objectId);
			}

			return $result->getDeletedCount() > 0;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Deletes a document matched by its _id property.
	 */
	public function deleteOne(object $document): bool {
		try {
			$result = $this->collection->deleteOne([
				'_id' => $document->_id
			]);
			if ($result->getDeletedCount() > 0) {
				$this->propagateSnapshots($document->_id);
			}

			return $result->getDeletedCount() > 0;
		} catch (\Exception $e) {
			throw $e;
		}
	}
	
	/**
	 * Deletes documents matching a filter.
	 */
	public function delete(array $filter): bool {
		try {
			// Deleted _ids can't be read afterwards: collect them first, if needed
			$ids = $this->embeddedIn() ? $this->collection->distinct('_id', $filter) : [];
			$result = $this->collection->deleteMany($filter);
			foreach ($ids as $id) {
				$this->propagateSnapshots($id);
			}
			return $result->getDeletedCount();
		} catch (\Exception $e) {
			throw $e;
		}
	}
	
	/**
	 * Applies a raw Mongo update (`$set`, `$unset`…) to every document matching the filter.
	 * Bypasses both behaviors and snapshot propagation.
	 * @return int number of modified documents
	 */
	public function updateMany(array $filter, array $update): int {
		return $this->collection->updateMany($filter, $update)->getModifiedCount();
	}

	/**
	 * Resyncs this document's snapshots in their targets (see embeddedIn()).
	 * One SnapshotSyncJob per target: run right away when `sync`, otherwise pushed to the Queue.
	 * @param array|null $dirty changed fields of the source: only propagated if they touch
	 *                          the snapshot. Null = deletion, only when onDelete ≠ keep.
	 */
	protected function propagateSnapshots(ObjectId $id, ?array $dirty = null): void {
		foreach ($this->snapshotTargets() as $target) {
			if ($dirty === null ? $target['onDelete'] === 'keep' : !array_intersect($dirty, $target['class']::syncedFields())) {
				continue;
			}
			$job = new SnapshotSyncJob(['source' => static::class, 'source_id' => $id, 'target' => $target]);
			$target['sync'] ? $job->run() : Queue::push($job);
		}
	}

	/**
	 * Returns the number of documents matching the filter.
	 */
	public function count(array $filter = []): int {
		return $this->collection->countDocuments($filter);
	}
	
	/**
	 * Runs a MongoDB aggregation pipeline and returns the results as an array.
	 */
	public function aggregate(array $pipeline): array {
		$cursor = $this->collection->aggregate($pipeline);
		return iterator_to_array($cursor, false);
	}
	
	/**
	 * Returns a paginated result set wrapped in a Paginator.
	 * Merges default paginator config with the provided info (page, limit, sort).
	 * @param array $filter       MongoDB filter
	 * @param array $paginateInfo page, limit and sort — typically from Request::paginateInfo()
	 */
	public function paginate(array $filter = [], array $paginateInfo = [], array $with = []): Paginator {
		$paginateInfo = array_merge(Config::get('paginator'),$paginateInfo);

		$page = $paginateInfo['page'] ?? 1;
		$limit = $paginateInfo['limit'] ?? 10;
		$sort = $paginateInfo['sort'] ?? [];
		$display = $this->getDisplayField();

		// Le tri n'est accepté que sur un champ déclaré triable dans getDisplayField :
		// la définition d'affichage fait office de whitelist (champ venant de l'URL).
		// L'ordre est normalisé à 1/-1 pour éviter un sort MongoDB invalide.
		if (!empty($sort)) {
			$field = array_key_first($sort);
			if (empty($display[$field]['sort'])) {
				$sort = [];
			} else {
				$sort = [$field => ($sort[$field] < 0 ? -1 : 1)];
			}
		}

		// Un tri stable est toujours nécessaire : sans lui, skip/limit peut renvoyer
		// des résultats incohérents entre deux pages (doublons / oublis). `_id` est le
		// seul champ présent sur tout document, donc le défaut universel.
		if (empty($sort)) {
			$sort = ['_id' => -1];
		}
		$paginateInfo['sort'] = $sort;

		$skip = ($page - 1) * $limit;

		$options = [
			'limit' => $limit,
			'skip'  => $skip,
			'sort'  => $sort,
			'with'  => $with,
		];

		$items = $this->find($filter, $options);
		$paginateInfo['total'] = $this->count($filter);

		$paginator = new Paginator($items, $paginateInfo);
		$paginator->setDisplayField($display);
		$paginator->setDocumentClass($this->documentClass);
		return $paginator;
	}

	/**
	 * Index Mongo à créer pour cette collection (format attendu par
	 * MongoDB\Collection::createIndexes(), ex. `[['key' => ['email' => 1], 'unique' => true]]`).
	 * Aucun par défaut ; à surcharger dans une Collection concrète. Lu par la commande `db-sync`.
	 * @return array<int, array{key: array, unique?: bool, name?: string, expireAfterSeconds?: int}>
	 */
	public function getIndexes(): array {
		return [];
	}

	/**
	 * Crée/synchronise les index déclarés par getIndexes() sur la collection Mongo.
	 * Idempotent : createIndexes ignore un index déjà existant avec la même définition.
	 */
	public function syncIndexes(): void {
		$indexes = $this->getIndexes();
		if (!empty($indexes)) {
			MongoClient::getInstance()->createIndex($this->collectionName, $indexes);
		}
		// Index on the snapshot reference, on the target side: propagation finds snapshots through it
		foreach ($this->snapshotTargets() as $target) {
			$target['collection']::getInstance()->collection->createIndex([$target['field'] . '._id' => 1]);
		}
	}

	/**
	 * Relations of this document, by name (the name read on the document: `$post->user`):
	 * - `type`       : 'belongsTo' (the key is on this side) or 'hasMany' (the key is on theirs)
	 * - `collection` : related Collection class
	 * - `key`        : ObjectId field holding the link (`user_id` for belongsTo, `post_id` for hasMany)
	 * Loaded on demand when read, or in batch through the `with` option of find()/paginate().
	 * @return array<string, array{type: string, collection: string, key: string}>
	 */
	public function relations(): array {
		return [];
	}

	/**
	 * Loads relations on a list of documents: one `$in` query per relation,
	 * whatever the number of documents (no N+1). Result: belongsTo → document
	 * or null, hasMany → array (empty if none). Stored via MasterDocument::setRelation().
	 * @param MasterDocument[] $documents
	 * @param string[]         $with      relation names declared in relations()
	 * @throws \InvalidArgumentException if a relation or its type is unknown
	 */
	public function loadRelations(array $documents, array $with): void {
		$relations = $this->relations();
		foreach ($with as $name) {
			$relation = $relations[$name] ?? throw new \InvalidArgumentException(static::class . " : relation '{$name}' inconnue");
			$target = $relation['collection']::getInstance();
			$key = $relation['key'];

			if ($relation['type'] === 'belongsTo') {
				$ids = array_values(array_filter(array_map(fn($doc) => $doc->$key ?? null, $documents)));
				$found = [];
				foreach ($ids ? $target->find(['_id' => ['$in' => $ids]]) : [] as $doc) {
					$found[(string) $doc->_id] = $doc;
				}
				foreach ($documents as $doc) {
					$doc->setRelation($name, $found[(string) ($doc->$key ?? '')] ?? null);
				}
			} elseif ($relation['type'] === 'hasMany') {
				$ids = array_map(fn($doc) => $doc->_id, $documents);
				$grouped = [];
				foreach ($target->find([$key => ['$in' => $ids]]) as $doc) {
					$grouped[(string) $doc->$key][] = $doc;
				}
				foreach ($documents as $doc) {
					$doc->setRelation($name, $grouped[(string) $doc->_id] ?? []);
				}
			} else {
				throw new \InvalidArgumentException(static::class . " : type '{$relation['type']}' inconnu pour '{$name}' (belongsTo, hasMany)");
			}
		}
	}

	/**
	 * Where this document's snapshots (EmbeddedSnapshot) are embedded, one entry per target field:
	 * - `collection` : target Collection class (e.g. PostCollection::class)
	 * - `field`      : target document property holding the snapshot (e.g. 'author')
	 * - `class`      : snapshot class (e.g. UserSnapshot::class)
	 * - `sync`       : true (default) immediate propagation, false through the Queue
	 * - `onDelete`   : 'keep' (default) frozen snapshot, 'mark' → `_deleted` set to true, 'unset' → field removed
	 * None by default; override it. Read it through snapshotTargets() (defaults applied).
	 * @return array<int, array{collection: string, field: string, class: string, sync?: bool, onDelete?: string}>
	 */
	public function embeddedIn(): array {
		return [];
	}

	/**
	 * embeddedIn() with default values filled in.
	 * @throws \InvalidArgumentException if an onDelete value is unknown
	 */
	public function snapshotTargets(): array {
		$targets = [];
		foreach ($this->embeddedIn() as $target) {
			$target += ['sync' => true, 'onDelete' => 'keep'];
			if (!in_array($target['onDelete'], ['keep', 'mark', 'unset'], true)) {
				throw new \InvalidArgumentException(static::class . " : onDelete '{$target['onDelete']}' invalide (keep, mark, unset)");
			}
			$targets[] = $target;
		}
		return $targets;
	}

	/**
	 * Describes the columns to render in a generic table (the `part.table` view).
	 * Default: every public field of the document (except `_id`), all sortable.
	 * Override in a concrete Collection to customise labels, sort, fake fields
	 * (resolved via the Document `__get`), or per-cell `render`/`after` callbacks.
	 *
	 * Shape: `['field' => ['label' => string, 'sort' => bool, 'render'? => callable, 'after'? => callable]]`
	 * @return array<string, array>
	 */
	public function getDisplayField(): array {
		$fields = [];
		$reflection = new \ReflectionClass($this->documentClass);
		foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
			$name = $prop->getName();
			if ($name === '_id') {
				continue;
			}
			$fields[$name] = [
				'label' => ucfirst(str_replace('_', ' ', $name)),
				'sort'  => true,
			];
		}
		return $fields;
	}

	/**
	 * Route link used when this document is shown as a home widget.
	 * Default: the resource `show` page. Override in a concrete Collection to
	 * point elsewhere (e.g. PersonaCollection → the chat action).
	 * @param object $document the target document
	 * @return array route array for UrlBuilder
	 */
	public function widgetLink(object $document): array {
		$parts = explode('\\', $this->documentClass);
		$controller = end($parts);
		return [
			'controller' => $controller,
			'action'     => 'show',
			'params'     => [lcfirst($controller) => $document->_id],
		];
	}
	
	/**
	 * Creates a MongoDB ObjectId from a string, or a new one if no string is provided.
	 */
	public function createId(?string $id = null): ObjectId {
		return $id ? new ObjectId($id) : new ObjectId();
	}
}