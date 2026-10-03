<?php

namespace SkankyTest\Integration\Model;

use DateTime;
use MongoDB\BSON\ObjectId;
use SkankyDev\Database\MongoClient;
use SkankyDev\Model\MasterCollection;
use SkankyDev\Model\Document\EmbeddedSnapshot;
use SkankyDev\Queue\Job\SnapshotSyncJob;
use SkankyDev\Queue\Queue;
use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Model\Document\Traits\TimedTrait;
use SkankyDev\Utilities\Paginator;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyTest\IntegrationTestCase;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class TestItem extends MasterDocument
{
    public string $name  = '';
    public int    $value = 0;
}

class TestItemCollection extends MasterCollection
{
    protected string $collectionName = 'test_items';
    protected string $documentClass  = TestItem::class;
    // TestItem n'utilise aucun trait de behavior → aucun behavior, isolation garantie
}

// ── Fixtures avec Behaviors ───────────────────────────────────────────────────

class TimedItem extends MasterDocument
{
    use TimedTrait; // déclare created_at / updated_at et active TimedBehavior par convention

    public string $name = '';
}

class TimedItemCollection extends MasterCollection
{
    protected string $collectionName = 'timed_items';
    protected string $documentClass  = TimedItem::class;
    // TimedBehavior est résolu depuis le TimedTrait de TimedItem
}

// ── Fixtures avec Snapshots ───────────────────────────────────────────────────

class Author extends MasterDocument
{
    public string $name = '';
    public string $bio  = ''; // hors snapshot
}

class AuthorSnapshot extends EmbeddedSnapshot
{
    public string $name = '';
}

class Article extends MasterDocument
{
    public string $title = '';
    public ?AuthorSnapshot $author = null;
}

class ArticleCollection extends MasterCollection
{
    use Singleton;

    protected string $collectionName = 'test_articles';
    protected string $documentClass  = Article::class;
}

class AuthorCollection extends MasterCollection
{
    use Singleton;

    protected string $collectionName = 'test_authors';
    protected string $documentClass  = Author::class;

    public function embeddedIn(): array
    {
        return [
            ['collection' => ArticleCollection::class, 'field' => 'author', 'class' => AuthorSnapshot::class],
        ];
    }
}

class MarkAuthorCollection extends AuthorCollection
{
    use Singleton;

    public function embeddedIn(): array
    {
        return [['collection' => ArticleCollection::class, 'field' => 'author', 'class' => AuthorSnapshot::class, 'onDelete' => 'mark']];
    }
}

class UnsetAuthorCollection extends AuthorCollection
{
    use Singleton;

    public function embeddedIn(): array
    {
        return [['collection' => ArticleCollection::class, 'field' => 'author', 'class' => AuthorSnapshot::class, 'onDelete' => 'unset']];
    }
}

class AsyncAuthorCollection extends AuthorCollection
{
    use Singleton;

    public function embeddedIn(): array
    {
        return [['collection' => ArticleCollection::class, 'field' => 'author', 'class' => AuthorSnapshot::class, 'sync' => false]];
    }
}

class BadOnDeleteCollection extends AuthorCollection
{
    use Singleton;

    public function embeddedIn(): array
    {
        return [
            ['collection' => ArticleCollection::class, 'field' => 'author', 'class' => AuthorSnapshot::class, 'onDelete' => 'cascade'],
        ];
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class MasterCollectionTest extends IntegrationTestCase
{
    private TestItemCollection $col;

    protected function setUp(): void
    {
        parent::setUp();

        // Collection vide avant chaque test — on instancie directement,
        // pas via le Singleton, donc pas besoin de reset $_instance
        $this->dropCollection('test_items');

        $this->col = new TestItemCollection();
    }

    // ── insert ────────────────────────────────────────────────────────────────

    public function testInsertPopulatesId(): void
    {
        $item = new TestItem(['name' => 'lumière', 'value' => 42]);
        $this->col->insert($item);

        $this->assertNotNull($item->_id);
        $this->assertInstanceOf(ObjectId::class, $item->_id);
    }

    public function testInsertReturnsTrueOnSuccess(): void
    {
        $item = new TestItem(['name' => 'test', 'value' => 1]);
        $this->assertTrue($this->col->insert($item));
    }

    // ── count ─────────────────────────────────────────────────────────────────

    public function testCountReturnsZeroOnEmptyCollection(): void
    {
        $this->assertEquals(0, $this->col->count());
    }

    public function testCountReflectsInserts(): void
    {
        $this->col->insert(new TestItem(['name' => 'a', 'value' => 1]));
        $this->col->insert(new TestItem(['name' => 'b', 'value' => 2]));
        $this->assertEquals(2, $this->col->count());
    }

    // ── find ─────────────────────────────────────────────────────────────────

    public function testFindReturnsAllDocuments(): void
    {
        $this->col->insert(new TestItem(['name' => 'x', 'value' => 10]));
        $this->col->insert(new TestItem(['name' => 'y', 'value' => 20]));

        $results = $this->col->find();
        $this->assertCount(2, $results);
    }

    public function testFindWithFilter(): void
    {
        $this->col->insert(new TestItem(['name' => 'alpha', 'value' => 1]));
        $this->col->insert(new TestItem(['name' => 'beta',  'value' => 2]));

        $results = $this->col->find(['value' => 1]);
        $this->assertCount(1, $results);
    }

    // ── findOne ───────────────────────────────────────────────────────────────

    public function testFindOneReturnsNullOnEmpty(): void
    {
        $this->assertNull($this->col->findOne());
    }

    public function testFindOneReturnsDocument(): void
    {
        $this->col->insert(new TestItem(['name' => 'unique', 'value' => 99]));
        $result = $this->col->findOne(['value' => 99]);
        $this->assertNotNull($result);
    }

    // ── findById ──────────────────────────────────────────────────────────────

    public function testFindByIdReturnsCorrectDocument(): void
    {
        $item = new TestItem(['name' => 'par-id', 'value' => 7]);
        $this->col->insert($item);

        $found = $this->col->findById((string) $item->_id);
        $this->assertNotNull($found);
    }

    public function testFindByIdReturnsNullForInvalidId(): void
    {
        $this->assertNull($this->col->findById('pas-un-objectid'));
    }

    public function testFindByIdReturnsNullForUnknownId(): void
    {
        $this->assertNull($this->col->findById((string) new ObjectId()));
    }

    // ── save / update ─────────────────────────────────────────────────────────

    public function testSaveInsertsNewDocument(): void
    {
        $item = new TestItem(['name' => 'nouveau', 'value' => 5]);
        $this->col->save($item);

        $this->assertNotNull($item->_id);
        $this->assertEquals(1, $this->col->count());
    }

    public function testUpdateModifiesDocument(): void
    {
        $item = new TestItem(['name' => 'avant', 'value' => 1]);
        $this->col->insert($item);

        $item->name  = 'après';
        $item->value = 100;
        $this->col->update($item);

        $found = $this->col->findById((string) $item->_id);
        $this->assertNotNull($found);
        // Le document mis à jour existe toujours en base
        $this->assertEquals(1, $this->col->count());
    }

    public function testUpdateThrowsWithoutId(): void
    {
        $item = new TestItem(['name' => 'sans-id', 'value' => 0]);
        $this->expectException(\Exception::class);
        $this->col->update($item);
    }

    public function testSaveDelegatesToUpdateWhenIdPresent(): void
    {
        $item = new TestItem(['name' => 'v1', 'value' => 1]);
        $this->col->insert($item);

        $item->name = 'v2';
        $this->col->save($item);

        $this->assertEquals(1, $this->col->count());
    }

    // ── dirty tracking ────────────────────────────────────────────────────────

    public function testInsertedDocumentIsClean(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);
        $this->assertFalse($item->isDirty());
    }

    public function testFoundDocumentIsClean(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);
        $found = $this->col->findById((string) $item->_id);
        $this->assertTrue($found->hasOriginal());
        $this->assertFalse($found->isDirty());
    }

    public function testUpdateWithoutChangesDoesNotWrite(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);
        $found = $this->col->findById((string) $item->_id);
        $this->assertFalse($this->col->update($found));
    }

    public function testUpdateLeavesDocumentClean(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);
        $item->name = 'b';
        $this->assertTrue($this->col->update($item));
        $this->assertFalse($item->isDirty());
    }

    public function testConcurrentUpdatesDoNotOverwriteEachOther(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);

        // Deux instances du même document chargées « en parallèle »
        $first  = $this->col->findById((string) $item->_id);
        $second = $this->col->findById((string) $item->_id);

        $first->name = 'modifié par first';
        $this->col->update($first);

        $second->value = 99;
        $this->col->update($second); // ne doit écrire que `value`

        $found = $this->col->findById((string) $item->_id);
        $this->assertSame('modifié par first', $found->name);
        $this->assertSame(99, $found->value);
    }

    public function testUpdateOfHandBuiltDocumentWritesAllFields(): void
    {
        $item = new TestItem(['name' => 'a', 'value' => 1]);
        $this->col->insert($item);

        // Document construit à la main avec un _id existant : pas d'état connu → $set complet
        $copy = new TestItem(['name' => 'b', 'value' => 2]);
        $copy->_id = $item->_id;
        $this->col->update($copy);

        $found = $this->col->findById((string) $item->_id);
        $this->assertSame('b', $found->name);
        $this->assertSame(2, $found->value);
    }

    // ── snapshots : config ────────────────────────────────────────────────────

    public function testEmbeddedInIsEmptyByDefault(): void
    {
        $this->assertSame([], $this->col->snapshotTargets());
    }

    public function testSnapshotTargetsAppliesDefaults(): void
    {
        $target = (new AuthorCollection())->snapshotTargets()[0];
        $this->assertTrue($target['sync']);
        $this->assertSame('keep', $target['onDelete']);
        $this->assertSame('author', $target['field']);
    }

    public function testSnapshotTargetsRejectsUnknownOnDelete(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new BadOnDeleteCollection())->snapshotTargets();
    }

    public function testSyncIndexesCreatesSnapshotIndexOnTarget(): void
    {
        $this->dropCollection('test_articles');
        (new AuthorCollection())->syncIndexes();

        $keys = [];
        foreach (MongoClient::getInstance()->getCollection('test_articles')->listIndexes() as $index) {
            $keys[] = $index->getKey();
        }
        $this->assertContains(['author._id' => 1], $keys);
    }

    // ── snapshots : propagation ───────────────────────────────────────────────

    /** Un auteur sauvegardé + un article qui porte son snapshot. */
    private function authorWithArticle(MasterCollection $authors): array
    {
        $this->dropCollection('test_authors');
        $this->dropCollection('test_articles');
        $this->dropCollection('jobs');

        $author = new Author(['name' => 'Simon']);
        $authors->insert($author);

        $article = new Article(['title' => 'Premier post']);
        $article->author = new AuthorSnapshot($author);
        (new ArticleCollection())->insert($article);

        return [$author, $article];
    }

    private function reloadArticle(Article $article): Article
    {
        return (new ArticleCollection())->findById((string) $article->_id);
    }

    public function testUpdateOfSnapshotFieldPropagatesToTarget(): void
    {
        $authors = new AuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $author->name = 'Skanky';
        $authors->update($author);

        $found = $this->reloadArticle($article);
        $this->assertInstanceOf(AuthorSnapshot::class, $found->author);
        $this->assertSame('Skanky', $found->author->name);
        $this->assertEquals($author->_id, $found->author->_id);
    }

    public function testUpdateOfFieldOutsideSnapshotDoesNotPropagate(): void
    {
        $authors = new AuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);
        // snapshot volontairement périmé : s'il y avait propagation, il serait réécrit
        (new ArticleCollection())->updateMany([], ['$set' => ['author.name' => 'périmé']]);

        $author->bio = 'dev PHP';
        $authors->update($author);

        $this->assertSame('périmé', $this->reloadArticle($article)->author->name);
    }

    public function testStaleTargetSaveDoesNotOverwritePropagatedSnapshot(): void
    {
        $authors = new AuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);
        $loadedBefore = $this->reloadArticle($article); // chargé avant la propagation

        $author->name = 'Skanky';
        $authors->update($author);

        $loadedBefore->title = 'Titre modifié';
        (new ArticleCollection())->update($loadedBefore); // n'écrit que `title`

        $found = $this->reloadArticle($article);
        $this->assertSame('Titre modifié', $found->title);
        $this->assertSame('Skanky', $found->author->name);
    }

    public function testDeleteWithKeepLeavesSnapshotUntouched(): void
    {
        $authors = new AuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $authors->deleteOne($author);

        $found = $this->reloadArticle($article);
        $this->assertSame('Simon', $found->author->name);
        $this->assertFalse($found->author->_deleted);
    }

    public function testDeleteWithMarkFlagsSnapshot(): void
    {
        $authors = new MarkAuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $authors->deleteById((string) $author->_id);

        $found = $this->reloadArticle($article);
        $this->assertTrue($found->author->_deleted);
        $this->assertSame('Simon', $found->author->name);
    }

    public function testDeleteWithUnsetRemovesSnapshot(): void
    {
        $authors = new UnsetAuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $authors->deleteOne($author);

        $this->assertNull($this->reloadArticle($article)->author);
    }

    public function testBulkDeletePropagatesToEachSource(): void
    {
        $authors = new MarkAuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $authors->delete(['name' => 'Simon']);

        $this->assertTrue($this->reloadArticle($article)->author->_deleted);
    }

    public function testAsyncPropagationGoesThroughQueue(): void
    {
        $authors = new AsyncAuthorCollection();
        [$author, $article] = $this->authorWithArticle($authors);

        $author->name = 'Skanky';
        $authors->update($author);

        // Pas encore propagé : le job attend dans la Queue
        $this->assertSame('Simon', $this->reloadArticle($article)->author->name);
        $jobDoc = Queue::next();
        $this->assertInstanceOf(SnapshotSyncJob::class, $jobDoc->payload);

        $jobDoc->payload->run(); // ce que fait queue-work
        $this->assertSame('Skanky', $this->reloadArticle($article)->author->name);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteOneRemovesDocument(): void
    {
        $item = new TestItem(['name' => 'à supprimer', 'value' => 0]);
        $this->col->insert($item);
        $this->assertEquals(1, $this->col->count());

        $this->col->deleteOne($item);
        $this->assertEquals(0, $this->col->count());
    }

    public function testDeleteByIdRemovesDocument(): void
    {
        $item = new TestItem(['name' => 'delete-by-id', 'value' => 0]);
        $this->col->insert($item);

        $this->assertTrue($this->col->deleteById((string) $item->_id));
        $this->assertEquals(0, $this->col->count());
    }

    public function testDeleteByIdReturnsFalseForUnknownId(): void
    {
        $this->assertFalse($this->col->deleteById((string) new ObjectId()));
    }

    // ── paginate ──────────────────────────────────────────────────────────────

    public function testPaginateReturnsPaginator(): void
    {
        foreach (range(1, 5) as $i) {
            $this->col->insert(new TestItem(['name' => "item-{$i}", 'value' => $i]));
        }

        $paginator = $this->col->paginate([], ['page' => 1, 'limit' => 3]);
        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertCount(3, $paginator->data);
    }

    public function testPaginateTotalReflectsAllDocuments(): void
    {
        foreach (range(1, 7) as $i) {
            $this->col->insert(new TestItem(['name' => "x-{$i}", 'value' => $i]));
        }

        $info = $this->col->paginate([], ['page' => 1, 'limit' => 3])->getOption();
        $this->assertEquals(7, $info['total']);
    }

    // ── createId ─────────────────────────────────────────────────────────────

    public function testCreateIdReturnsObjectId(): void
    {
        $id = $this->col->createId();
        $this->assertInstanceOf(ObjectId::class, $id);
    }

    public function testCreateIdFromStringReturnsMatchingObjectId(): void
    {
        $original = new ObjectId();
        $fromStr  = $this->col->createId((string) $original);
        $this->assertEquals((string) $original, (string) $fromStr);
    }

    // ── aggregate ─────────────────────────────────────────────────────────────

    public function testAggregateReturnsResults(): void
    {
        $this->col->insert(new TestItem(['name' => 'a', 'value' => 1]));
        $this->col->insert(new TestItem(['name' => 'b', 'value' => 1]));
        $this->col->insert(new TestItem(['name' => 'c', 'value' => 2]));

        $pipeline = [
            ['$group' => ['_id' => '$value', 'count' => ['$sum' => 1]]],
            ['$sort'  => ['_id' => 1]],
        ];

        $results = $this->col->aggregate($pipeline);
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]['count']); // value=1 → 2 documents
        $this->assertEquals(1, $results[1]['count']); // value=2 → 1 document
    }

    // ── delete() ─────────────────────────────────────────────────────────────

    public function testDeleteRemovesMatchingDocuments(): void
    {
        $this->col->insert(new TestItem(['name' => 'del-a', 'value' => 99]));
        $this->col->insert(new TestItem(['name' => 'del-b', 'value' => 99]));
        $this->col->insert(new TestItem(['name' => 'keep', 'value' => 1]));

        $this->col->delete(['value' => 99]);

        $this->assertEquals(1, $this->col->count());
        $remaining = $this->col->findOne();
        $this->assertEquals('keep', $remaining->name);
    }

    // ── callBehaviors via TimedBehavior ───────────────────────────────────────

    // ── getDisplayField (défaut par réflexion) ─────────────────────────────────

    public function testGetDisplayFieldListsPublicFieldsExceptId(): void
    {
        $fields = $this->col->getDisplayField();

        // TestItem a deux champs publics : name, value (mais pas _id)
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('value', $fields);
        $this->assertArrayNotHasKey('_id', $fields);

        $this->assertSame(['label' => 'Name', 'sort' => true], $fields['name']);
        $this->assertSame(['label' => 'Value', 'sort' => true], $fields['value']);
    }

    // ── Tri whitelisté via getDisplayField ──────────────────────────────────────

    public function testPaginateKeepsSortOnDeclaredSortableField(): void
    {
        $p = $this->col->paginate([], ['page' => 1, 'sort' => ['name' => 1]]);
        $this->assertSame(['name' => 1], $p->getOption()['sort']);
    }

    public function testPaginateRejectsSortOnUnknownField(): void
    {
        // 'hacky' n'est pas dans getDisplayField → ignoré → tri stable par défaut
        $p = $this->col->paginate([], ['page' => 1, 'sort' => ['hacky' => 1]]);
        $this->assertSame(['_id' => -1], $p->getOption()['sort']);
    }

    public function testPaginateNormalizesSortOrder(): void
    {
        // un order farfelu venant de l'URL est ramené à 1 / -1
        $p = $this->col->paginate([], ['page' => 1, 'sort' => ['name' => 5]]);
        $this->assertSame(['name' => 1], $p->getOption()['sort']);
    }

    // ── widgetLink (lien par défaut d'un widget = show de la ressource) ─────────

    public function testWidgetLinkDefaultsToResourceShow(): void
    {
        $doc = (object) ['_id' => 'abc123'];
        $link = $this->col->widgetLink($doc);

        $this->assertSame('TestItem', $link['controller']);
        $this->assertSame('show', $link['action']);
        $this->assertSame(['testItem' => 'abc123'], $link['params']);
    }

    public function testBehaviorSetsTimestampsOnInsert(): void
    {
        $this->dropCollection('timed_items');
        $col  = new TimedItemCollection();
        $item = new TimedItem(['name' => 'with-behavior']);

        $col->insert($item);

        $this->assertInstanceOf(DateTime::class, $item->created_at);
        $this->assertInstanceOf(DateTime::class, $item->updated_at);
    }

    public function testBehaviorSetsUpdatedAtOnUpdate(): void
    {
        $this->dropCollection('timed_items');
        $col  = new TimedItemCollection();
        $item = new TimedItem(['name' => 'before']);
        $col->insert($item);

        $item->name = 'after';
        $col->update($item);

        $updated = $col->findById((string) $item->_id);
        $this->assertNotNull($updated->updated_at);
    }
}
