<?php

namespace SkankyTest\Integration\Database;

use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\Manager;
use SkankyDev\Database\MongoClient;
use SkankyTest\IntegrationTestCase;

class MongoClientTest extends IntegrationTestCase
{
    public function testGetDbNameReturnsTestDatabase(): void
    {
        $this->assertEquals('skankydev_test', MongoClient::_getDbName());
    }

    public function testGetDatabaseReturnsDefaultDatabase(): void
    {
        $db = MongoClient::_getDatabase();
        $this->assertInstanceOf(Database::class, $db);
        $this->assertEquals('skankydev_test', $db->getDatabaseName());
    }

    public function testGetDatabaseWithExplicitName(): void
    {
        $db = MongoClient::_getDatabase('autre_db');
        $this->assertInstanceOf(Database::class, $db);
        $this->assertEquals('autre_db', $db->getDatabaseName());
    }

    public function testGetCollectionReturnsCollection(): void
    {
        $col = MongoClient::_getCollection('test_ping');
        $this->assertInstanceOf(Collection::class, $col);
        $this->assertEquals('test_ping', $col->getCollectionName());
    }

    public function testGetManagerReturnsManager(): void
    {
        $manager = MongoClient::_getManager();
        $this->assertInstanceOf(Manager::class, $manager);
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $a = MongoClient::getInstance();
        $b = MongoClient::getInstance();
        $this->assertSame($a, $b);
    }
}
