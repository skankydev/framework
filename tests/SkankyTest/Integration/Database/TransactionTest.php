<?php

namespace SkankyTest\Integration\Database;

use SkankyDev\Config\Config;
use SkankyDev\Database\MongoClient;
use SkankyDev\Model\Document\EmbeddedSnapshot;
use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Model\JobCollection;
use SkankyDev\Model\MasterCollection;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyTest\IntegrationTestCase;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class TxAuthor extends MasterDocument
{
    public string $name = '';
}

class TxAuthorSnapshot extends EmbeddedSnapshot
{
    public string $name = '';
}

class TxArticle extends MasterDocument
{
    public string $title = '';
    public ?TxAuthorSnapshot $author = null;
}

class TxArticleCollection extends MasterCollection
{
    use Singleton;

    protected string $collectionName = 'test_tx_articles';
    protected string $documentClass  = TxArticle::class;
}

class TxAuthorCollection extends MasterCollection
{
    use Singleton;

    /** Propagation immédiate (true) ou via la Queue (false) */
    public static bool $sync = true;

    protected string $collectionName = 'test_tx_authors';
    protected string $documentClass  = TxAuthor::class;

    public function embeddedIn(): array
    {
        return [
            ['collection' => TxArticleCollection::class, 'field' => 'author', 'class' => TxAuthorSnapshot::class, 'sync' => self::$sync],
        ];
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class TransactionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Les singletons gardent les Collection du client précédent : une session
        // ne peut servir qu'avec le client qui l'a créée
        foreach ([TxAuthorCollection::class, TxArticleCollection::class, JobCollection::class] as $class) {
            (new \ReflectionProperty($class, '_instance'))->setValue(null, null);
        }
        $this->dropCollection('test_tx_authors');
        $this->dropCollection('test_tx_articles');
        $this->dropCollection('jobs');
        TxAuthorCollection::$sync = true;
    }

    protected function tearDown(): void
    {
        Config::set('db.MongoDB.transaction', false);
        foreach (glob(APP_FOLDER . '/logs/*.log') ?: [] as $f) {
            unlink($f);
        }
    }

    // ── désactivée (par défaut) ──────────────────────────────────────────────

    public function testDisabledRunsCallbackWithoutSession(): void
    {
        $session = 'pas appelé';
        $result = MongoClient::_transaction(function () use (&$session) {
            $session = MongoClient::_getSession();
            return 42;
        });

        $this->assertEquals(42, $result);
        $this->assertNull($session);
    }

    public function testDisabledDoesNotRollback(): void
    {
        try {
            MongoClient::_transaction(function () {
                TxArticleCollection::_save(new TxArticle(['title' => 'gardé']));
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
        }

        $this->assertEquals(1, TxArticleCollection::_count());
    }

    public function testEnabledOnStandaloneThrowsOnFirstWrite(): void
    {
        if ($this->isReplicaSet()) {
            $this->markTestSkipped('MongoDB est en replica set');
        }
        Config::set('db.MongoDB.transaction', true);

        $this->expectException(\MongoDB\Driver\Exception\Exception::class);
        MongoClient::_transaction(fn() => TxArticleCollection::_save(new TxArticle(['title' => 'x'])));
    }

    // ── activée (replica set requis) ─────────────────────────────────────────

    public function testEnabledCommitsAllWrites(): void
    {
        $this->enableTransactions();

        MongoClient::_transaction(function () {
            TxArticleCollection::_save(new TxArticle(['title' => 'a']));
            TxArticleCollection::_save(new TxArticle(['title' => 'b']));
        });

        $this->assertEquals(2, TxArticleCollection::_count());
        $this->assertNull(MongoClient::_getSession());
    }

    public function testEnabledRollsBackOnException(): void
    {
        $this->enableTransactions();

        try {
            MongoClient::_transaction(function () {
                TxArticleCollection::_save(new TxArticle(['title' => 'annulé']));
                throw new \RuntimeException('boom');
            });
            $this->fail('L\'exception aurait dû remonter');
        } catch (\RuntimeException $e) {
            $this->assertEquals('boom', $e->getMessage());
        }

        $this->assertEquals(0, TxArticleCollection::_count());
        $this->assertNull(MongoClient::_getSession());
    }

    public function testNestedTransactionReusesSession(): void
    {
        $this->enableTransactions();

        $sessions = [];
        MongoClient::_transaction(function () use (&$sessions) {
            $sessions[] = MongoClient::_getSession();
            MongoClient::_transaction(function () use (&$sessions) {
                $sessions[] = MongoClient::_getSession();
            });
        });

        $this->assertNotNull($sessions[0]);
        $this->assertSame($sessions[0], $sessions[1]);
    }

    public function testReadInsideTransactionSeesItsOwnWrites(): void
    {
        $this->enableTransactions();

        $count = MongoClient::_transaction(function () {
            TxArticleCollection::_save(new TxArticle(['title' => 'pas encore commité']));
            return TxArticleCollection::_count();
        });

        $this->assertEquals(1, $count);
    }

    // ── propagation des snapshots ────────────────────────────────────────────

    public function testSyncPropagationReadsUpdatedSource(): void
    {
        $this->enableTransactions();
        [$author, $article] = $this->authorWithArticle();

        $author->name = 'Nouveau nom';
        TxAuthorCollection::_save($author);

        // SnapshotSyncJob relit la source dans la transaction : il voit le nouveau nom
        $this->assertEquals('Nouveau nom', TxArticleCollection::_findById((string) $article->_id)->author->name);
    }

    public function testQueuedPropagationIsPushedWithSourceUpdate(): void
    {
        $this->enableTransactions();
        TxAuthorCollection::$sync = false;
        [$author] = $this->authorWithArticle();

        $author->name = 'Nouveau nom';
        TxAuthorCollection::_save($author);

        $this->assertEquals('Nouveau nom', TxAuthorCollection::_findById((string) $author->_id)->name);
        $this->assertEquals(1, JobCollection::_count());
    }

    public function testRolledBackUpdateDoesNotPushPropagation(): void
    {
        $this->enableTransactions();
        TxAuthorCollection::$sync = false;
        [$author] = $this->authorWithArticle();

        try {
            MongoClient::_transaction(function () use ($author) {
                $author->name = 'Jamais écrit';
                TxAuthorCollection::_save($author);
                throw new \RuntimeException('crash');
            });
        } catch (\RuntimeException $e) {
        }

        $this->assertEquals('Auteur', TxAuthorCollection::_findById((string) $author->_id)->name);
        $this->assertEquals(0, JobCollection::_count());
    }

    public function testUpdateRetriedKeepsDirtyFieldsUntilCommit(): void
    {
        // syncOriginal() n'est appelé qu'après la transaction : le document est propre ensuite
        $this->enableTransactions();
        [$author] = $this->authorWithArticle();

        $author->name = 'Nouveau nom';
        TxAuthorCollection::_save($author);

        $this->assertFalse($author->isDirty());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function isReplicaSet(): bool
    {
        $hello = MongoClient::_getDatabase('admin')->command(['hello' => 1])->toArray()[0];
        return isset($hello['setName']);
    }

    private function enableTransactions(): void
    {
        if (!$this->isReplicaSet()) {
            $this->markTestSkipped('Transactions : MongoDB doit être en replica set');
        }
        Config::set('db.MongoDB.transaction', true);
    }

    /** @return array{TxAuthor, TxArticle} */
    private function authorWithArticle(): array
    {
        $author = new TxAuthor(['name' => 'Auteur']);
        TxAuthorCollection::_save($author);

        $article = new TxArticle(['title' => 'Article', 'author' => new TxAuthorSnapshot($author)]);
        TxArticleCollection::_save($article);

        return [$author, $article];
    }
}
