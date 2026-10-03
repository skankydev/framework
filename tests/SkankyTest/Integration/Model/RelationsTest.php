<?php

namespace SkankyTest\Integration\Model;

use MongoDB\BSON\ObjectId;
use SkankyDev\Model\MasterCollection;
use SkankyDev\Model\Document\MasterDocument;
use SkankyDev\Utilities\Traits\Singleton;
use SkankyTest\IntegrationTestCase;

// ── Fixtures (RelPost → RelPostCollection par convention, pour le lazy) ──────

class RelUser extends MasterDocument
{
    public string $name = '';
}

class RelPost extends MasterDocument
{
    public string $title = '';
    public ?ObjectId $user_id = null;
}

class RelComment extends MasterDocument
{
    public string $body = '';
    public ?ObjectId $post_id = null;
}

class RelUserCollection extends MasterCollection
{
    use Singleton;
    protected string $collectionName = 'test_rel_users';
    protected string $documentClass  = RelUser::class;
}

class RelCommentCollection extends MasterCollection
{
    use Singleton;
    protected string $collectionName = 'test_rel_comments';
    protected string $documentClass  = RelComment::class;
}

class RelPostCollection extends MasterCollection
{
    use Singleton;
    protected string $collectionName = 'test_rel_posts';
    protected string $documentClass  = RelPost::class;

    public function relations(): array
    {
        return [
            'user'     => ['type' => 'belongsTo', 'collection' => RelUserCollection::class, 'key' => 'user_id'],
            'comments' => ['type' => 'hasMany', 'collection' => RelCommentCollection::class, 'key' => 'post_id'],
            'bad'      => ['type' => 'hasOne', 'collection' => RelUserCollection::class, 'key' => 'user_id'],
        ];
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class RelationsTest extends IntegrationTestCase
{
    private RelPostCollection $posts;
    private RelUser $simon;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropCollection('test_rel_users');
        $this->dropCollection('test_rel_posts');
        $this->dropCollection('test_rel_comments');
        $this->posts = RelPostCollection::getInstance();

        // Simon : 2 posts, dont le premier a 2 commentaires ; + 1 post orphelin
        $this->simon = new RelUser(['name' => 'Simon']);
        RelUserCollection::_insert($this->simon);

        foreach (['p1', 'p2'] as $title) {
            $post = new RelPost(['title' => $title, 'user_id' => $this->simon->_id]);
            $this->posts->insert($post);
        }
        $this->posts->insert(new RelPost(['title' => 'orphelin', 'user_id' => new ObjectId()]));

        $p1 = $this->posts->findOne(['title' => 'p1']);
        foreach (['c1', 'c2'] as $body) {
            RelCommentCollection::_insert(new RelComment(['body' => $body, 'post_id' => $p1->_id]));
        }
    }

    public function testFindWithLoadsBelongsTo(): void
    {
        $posts = $this->posts->find(['title' => ['$in' => ['p1', 'p2']]], ['with' => ['user']]);

        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertInstanceOf(RelUser::class, $post->user);
            $this->assertSame('Simon', $post->user->name);
        }
    }

    public function testFindWithLoadsHasMany(): void
    {
        $posts = $this->posts->find([], ['with' => ['comments'], 'sort' => ['title' => 1]]);
        $byTitle = array_column(array_map(fn($p) => [$p->title, $p], $posts), 1, 0);

        $this->assertCount(2, $byTitle['p1']->comments);
        $this->assertContainsOnlyInstancesOf(RelComment::class, $byTitle['p1']->comments);
        $this->assertSame([], $byTitle['p2']->comments);
    }

    public function testMissingBelongsToIsNull(): void
    {
        $post = $this->posts->findOne(['title' => 'orphelin'], ['with' => ['user']]);
        $this->assertNull($post->user);
    }

    public function testRelationIsLoadedLazilyWithoutWith(): void
    {
        $post = $this->posts->findOne(['title' => 'p1']);

        $this->assertSame('Simon', $post->user->name);
        $this->assertCount(2, $post->comments);
    }

    public function testLazyRelationIsCached(): void
    {
        $post = $this->posts->findOne(['title' => 'p1']);
        $first = $post->user;
        $this->assertSame($first, $post->user); // même instance : pas de 2e requête
    }

    public function testFindByIdAcceptsWith(): void
    {
        $p1 = $this->posts->findOne(['title' => 'p1']);
        $post = $this->posts->findById((string) $p1->_id, ['with' => ['user', 'comments']]);

        $this->assertSame('Simon', $post->user->name);
        $this->assertCount(2, $post->comments);
    }

    public function testPaginateWithLoadsRelations(): void
    {
        $paginator = $this->posts->paginate(['title' => ['$in' => ['p1', 'p2']]], [], ['user']);

        $seen = 0;
        foreach ($paginator as $post) {
            $this->assertSame('Simon', $post->user->name);
            $seen++;
        }
        $this->assertSame(2, $seen);
    }

    public function testLoadedRelationIsNeitherPersistedNorDirty(): void
    {
        $post = $this->posts->findOne(['title' => 'p1'], ['with' => ['user']]);

        $this->assertFalse($post->isDirty());
        $this->assertArrayNotHasKey('user', $post->bsonSerialize());
        $this->assertArrayNotHasKey('user', $post->jsonSerialize());
    }

    public function testUnknownRelationThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->posts->find([], ['with' => ['nope']]);
    }

    public function testUnknownRelationTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->posts->find([], ['with' => ['bad']]);
    }

    public function testUndeclaredNameStillReturnsNull(): void
    {
        $post = $this->posts->findOne(['title' => 'p1']);
        $this->assertNull($post->nope);
    }
}
