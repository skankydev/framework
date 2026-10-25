<?php

namespace SkankyTest\Integration\Command;

use SkankyDev\Command\DbReplica;
use SkankyDev\Database\MongoClient;
use SkankyTest\IntegrationTestCase;

/**
 * Seuls les états qu'on peut constater sans les modifier sont testés :
 * l'initialisation elle-même (replSetInitiate) changerait la topologie du serveur de test.
 */
class DbReplicaTest extends IntegrationTestCase
{
    public function testAlreadyReplicaSetDoesNothing(): void
    {
        $hello = $this->hello();
        if (!isset($hello['setName'])) {
            $this->markTestSkipped('MongoDB n\'est pas un replica set initialisé');
        }

        $output = $this->runCommand();

        $this->assertStringContainsString(__('skankydev.cli.db_replica.already', ['name' => $hello['setName']]), $output);
    }

    public function testStandaloneExplainsTheConfig(): void
    {
        // isreplicaset n'existe que sur un membre pas encore initialisé, setName qu'une fois initialisé
        $hello = $this->hello();
        if (isset($hello['setName']) || !empty($hello['isreplicaset'])) {
            $this->markTestSkipped('MongoDB est lancé en replica set');
        }

        $output = $this->runCommand();

        $this->assertStringContainsString(__('skankydev.cli.db_replica.not_enabled'), $output);
        $this->assertStringContainsString('replSetName: rs0', $output);
        $this->assertStringContainsString(__('skankydev.cli.db_replica.restart'), $output);
    }

    private function hello(): array
    {
        return MongoClient::_getDatabase('admin')
            ->command(['hello' => 1], ['typeMap' => ['root' => 'array', 'document' => 'array']])
            ->toArray()[0];
    }

    private function runCommand(): string
    {
        ob_start();
        (new DbReplica())->run();
        return ob_get_clean();
    }
}
