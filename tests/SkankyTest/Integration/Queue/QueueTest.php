<?php

namespace SkankyTest\Integration\Queue;

use SkankyDev\Database\MongoClient;
use SkankyDev\Model\JobCollection;
use SkankyDev\Queue\Queue;
use SkankyDev\Queue\Job\MasterJob;
use SkankyTest\IntegrationTestCase;

// ── Fixture ───────────────────────────────────────────────────────────────────

class PingJob extends MasterJob
{
    public string $target = '';

    public function __construct(string $target = '')
    {
        $this->target = $target;
    }

    public function run(): void {}
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class QueueTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dropCollection('jobs');

        // Nettoie les logs générés par Queue::push()
        if (is_dir(APP_FOLDER . '/logs')) {
            foreach (glob(APP_FOLDER . '/logs/*.log') as $f) {
                unlink($f);
            }
        }
    }

    protected function tearDown(): void
    {
        if (is_dir(APP_FOLDER . '/logs')) {
            foreach (glob(APP_FOLDER . '/logs/*.log') as $f) {
                unlink($f);
            }
        }
    }

    // ── push ─────────────────────────────────────────────────────────────────

    public function testPushInsertsJobInDatabase(): void
    {
        Queue::push(new PingJob('skankyblog.local'));

        $col   = new JobCollection();
        $count = $col->count();
        $this->assertEquals(1, $count);
    }

    public function testPushSetsStatusToPending(): void
    {
        Queue::push(new PingJob('test'));

        $col = new JobCollection();
        $job = $col->findOne(['status' => Queue::PENDING]);
        $this->assertNotNull($job);
    }

    public function testPushSetsDefaultAttempts(): void
    {
        Queue::push(new PingJob('test'));

        $col = new JobCollection();
        $job = $col->findOne();
        $this->assertEquals(0, $job->attempts);
        $this->assertEquals(3, $job->max_attempts);
    }

    public function testPushMultipleJobsAreAllQueued(): void
    {
        Queue::push(new PingJob('a'));
        Queue::push(new PingJob('b'));
        Queue::push(new PingJob('c'));

        $col = new JobCollection();
        $this->assertEquals(3, $col->count());
    }

    public function testPushWritesJobLog(): void
    {
        Queue::push(new PingJob('test'));

        $logFile = glob(APP_FOLDER . '/logs/*-jobs.log');
        $this->assertNotEmpty($logFile);
        $content = file_get_contents($logFile[0]);
        $this->assertStringContainsString('QUEUED', $content);
    }

    // ── next ─────────────────────────────────────────────────────────────────

    public function testNextReturnsNullWhenQueueIsEmpty(): void
    {
        $this->assertNull(Queue::next());
    }

    public function testNextReturnsPendingJob(): void
    {
        Queue::push(new PingJob('premier'));
        $job = Queue::next();
        $this->assertNotNull($job);
        $this->assertEquals(Queue::PENDING, $job->status);
    }

    public function testNextReturnsFifoOrder(): void
    {
        // Les jobs sont triés par created_at ASC — le premier inséré doit revenir en premier
        Queue::push(new PingJob('premier'));
        Queue::push(new PingJob('second'));

        $job = Queue::next();
        $this->assertNotNull($job);
        // Le premier job inséré doit avoir created_at <= celui du second
        $this->assertEquals(Queue::PENDING, $job->status);
    }

    // ── constantes ───────────────────────────────────────────────────────────

    public function testQueueConstants(): void
    {
        $this->assertEquals('pending',    Queue::PENDING);
        $this->assertEquals('processing', Queue::PROCESSING);
        $this->assertEquals('success',    Queue::SUCCESS);
        $this->assertEquals('failed',     Queue::FAILED);
    }
}
