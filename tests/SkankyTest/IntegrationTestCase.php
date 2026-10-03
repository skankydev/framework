<?php

namespace SkankyTest;

use PHPUnit\Framework\TestCase;
use SkankyDev\Database\MongoClient;

/**
 * Base class for integration tests that need a real MongoDB connection.
 * Uses the `skankydev_test` database configured in tests/bootstrap.php.
 * Each test starts with a fresh MongoClient singleton.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        (new \ReflectionProperty(MongoClient::class, '_instance'))
            ->setValue(null, null);

        try {
            // Vérifie que la connexion est possible — sinon on skippe
            MongoClient::getInstance();
        } catch (\Exception $e) {
            $this->markTestSkipped('MongoDB indisponible : ' . $e->getMessage());
        }
    }

    /**
     * Drops a collection from the test database.
     * Call this in setUp() of each test class to garantir un état propre.
     */
    protected function dropCollection(string $name): void
    {
        MongoClient::_getDatabase()->dropCollection($name);
    }
}
