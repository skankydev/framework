<?php

namespace SkankyTest\Integration\Queue;

use MongoDB\BSON\UTCDateTime;
use SkankyDev\Command\QueueWork;
use SkankyDev\Database\MongoClient;
use SkankyDev\Model\Enum\JobStatus;
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

class SlowJob extends MasterJob
{
    public function run(): void {}

    public function timeout(): int
    {
        return 3600;
    }
}

class BrokenJob extends MasterJob
{
    public function run(): void
    {
        throw new \Error('boom');
    }
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
        $job = $col->findOne(['status' => JobStatus::PENDING->value]);
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

    public function testPushStoresJobTimeout(): void
    {
        Queue::push(new SlowJob());

        $job = (new JobCollection())->findOne();
        $this->assertEquals(3600, $job->timeout);
    }

    public function testNextClaimsPendingJob(): void
    {
        Queue::push(new PingJob('premier'));
        $job = Queue::next();
        $this->assertNotNull($job);
        $this->assertSame(JobStatus::PROCESSING, $job->status);
        $this->assertEquals(1, $job->attempts);
        $this->assertNotNull($job->started_at);
        $this->assertInstanceOf(PingJob::class, $job->payload);
    }

    public function testNextSetsLeaseFromJobTimeout(): void
    {
        Queue::push(new PingJob('lease'));
        $job = Queue::next();

        $lease = $job->lease_until->getTimestamp() - $job->started_at->getTimestamp();
        $this->assertEquals(600, $lease);
    }

    public function testNextIsPersistedAsProcessing(): void
    {
        Queue::push(new PingJob('persist'));
        Queue::next();

        $col = new JobCollection();
        $this->assertEquals(1, $col->count(['status' => JobStatus::PROCESSING->value]));
        $this->assertEquals(0, $col->count(['status' => JobStatus::PENDING->value]));
    }

    public function testNextReturnsFifoOrder(): void
    {
        // Les jobs sont triés par created_at ASC — le premier inséré doit revenir en premier
        Queue::push(new PingJob('premier'));
        usleep(2000); // created_at est à la milliseconde côté Mongo
        Queue::push(new PingJob('second'));

        $this->assertEquals('premier', Queue::next()->payload->target);
        $this->assertEquals('second',  Queue::next()->payload->target);
    }

    public function testNextNeverClaimsTheSameJobTwice(): void
    {
        Queue::push(new PingJob('seul'));

        $this->assertNotNull(Queue::next());
        $this->assertNull(Queue::next());
    }

    // ── bail (lease) ─────────────────────────────────────────────────────────

    public function testNextReclaimsJobWithExpiredLease(): void
    {
        Queue::push(new PingJob('bloque'));
        Queue::next();
        $this->expireLeases();

        $job = Queue::next();
        $this->assertNotNull($job);
        $this->assertEquals('bloque', $job->payload->target);
        $this->assertEquals(2, $job->attempts);
    }

    public function testNextIgnoresJobWithRunningLease(): void
    {
        Queue::push(new PingJob('en-cours'));
        Queue::next();

        $this->assertNull(Queue::next());
    }

    public function testExpiredLeaseWithoutAttemptsLeftMarksJobFailed(): void
    {
        Queue::push(new PingJob('mort'));
        Queue::next();
        $this->rawJobs()->updateMany([], ['$set' => ['attempts' => 3]]);
        $this->expireLeases();

        $this->assertNull(Queue::next());
        $job = (new JobCollection())->findOne();
        $this->assertSame(JobStatus::FAILED, $job->status);
        $this->assertEquals('Lease expired', $job->error);
    }

    public function testNextHandlesJobWithoutStoredTimeout(): void
    {
        // Job mis en queue avant l'ajout du champ timeout
        Queue::push(new PingJob('ancien'));
        $this->rawJobs()->updateMany([], ['$unset' => ['timeout' => '']]);

        $job = Queue::next();
        $this->assertNotNull($job->lease_until);
    }

    // ── worker (QueueWork::processJob) ───────────────────────────────────────

    public function testWorkerDeletesJobOnSuccess(): void
    {
        Queue::push(new PingJob('ok'));
        $this->process(Queue::next());

        $this->assertEquals(0, (new JobCollection())->count());
    }

    public function testWorkerPutsJobBackToPendingOnError(): void
    {
        // Une \Error (pas une \Exception) ne doit pas tuer le worker ni bloquer le job
        Queue::push(new BrokenJob());
        $this->process(Queue::next());

        $job = (new JobCollection())->findOne();
        $this->assertSame(JobStatus::PENDING, $job->status);
        $this->assertEquals(1, $job->attempts);
        $this->assertEquals('boom', $job->error);
    }

    public function testWorkerMarksJobFailedOnLastAttempt(): void
    {
        Queue::push(new BrokenJob());
        for ($i = 0; $i < 3; $i++) {
            $this->process(Queue::next());
        }

        $job = (new JobCollection())->findOne();
        $this->assertSame(JobStatus::FAILED, $job->status);
        $this->assertEquals(3, $job->attempts);
        $this->assertNull(Queue::next());
    }

    // ── enum ─────────────────────────────────────────────────────────────────

    public function testJobStatusValues(): void
    {
        $this->assertEquals('pending',    JobStatus::PENDING->value);
        $this->assertEquals('processing', JobStatus::PROCESSING->value);
        $this->assertEquals('failed',     JobStatus::FAILED->value);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function rawJobs(): \MongoDB\Collection
    {
        return MongoClient::_getCollection('jobs');
    }

    private function process(object $jobInfo): void
    {
        (new \ReflectionMethod(QueueWork::class, 'processJob'))->invoke(new QueueWork(), $jobInfo);
    }

    private function expireLeases(): void
    {
        $this->rawJobs()->updateMany([], ['$set' => ['lease_until' => new UTCDateTime((time() - 60) * 1000)]]);
    }
}
