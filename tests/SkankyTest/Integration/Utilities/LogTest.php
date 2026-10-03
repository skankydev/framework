<?php

namespace SkankyTest\Integration\Utilities;

use SkankyDev\Utilities\Log;
use SkankyTest\IntegrationTestCase;

class LogTest extends IntegrationTestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        // Pas besoin de MongoDB pour les logs — on override juste le setUp
        // pour ne pas tenter de connexion inutile
        $this->logDir = APP_FOLDER . '/logs';

        // Nettoie les logs de test avant chaque test
        if (is_dir($this->logDir)) {
            foreach (glob($this->logDir . '/*.log') as $file) {
                unlink($file);
            }
        }
    }

    protected function tearDown(): void
    {
        // Nettoie après aussi pour ne pas laisser de fichiers traîner
        if (is_dir($this->logDir)) {
            foreach (glob($this->logDir . '/*.log') as $file) {
                unlink($file);
            }
        }
    }

    private function todayLog(string $context = 'skankydev'): string
    {
        return $this->logDir . '/' . date('Y-m-d') . "-{$context}.log";
    }

    // ── info ─────────────────────────────────────────────────────────────────

    public function testInfoCreatesLogFile(): void
    {
        Log::info('démarrage');
        $this->assertFileExists($this->todayLog());
    }

    public function testInfoWritesMessageAndLevel(): void
    {
        Log::info('serveur démarré');
        $content = file_get_contents($this->todayLog());
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('serveur démarré', $content);
    }

    public function testInfoUsesCustomContext(): void
    {
        Log::info('message', 'mqtt');
        $this->assertFileExists($this->todayLog('mqtt'));
        $content = file_get_contents($this->todayLog('mqtt'));
        $this->assertStringContainsString('message', $content);
    }

    public function testInfoAppendsMultipleLines(): void
    {
        Log::info('ligne 1');
        Log::info('ligne 2');
        $content = file_get_contents($this->todayLog());
        $this->assertStringContainsString('ligne 1', $content);
        $this->assertStringContainsString('ligne 2', $content);
    }

    // ── warning ─────────────────────────��───────────────────────────────���─────

    public function testWarningWritesLevelAndMessage(): void
    {
        Log::warning('tension basse');
        $content = file_get_contents($this->todayLog());
        $this->assertStringContainsString('WARNING', $content);
        $this->assertStringContainsString('tension basse', $content);
    }

    // ── error ─────────────────────────────��───────────────────────────────────

    public function testErrorCreatesErrorLog(): void
    {
        Log::error(new \RuntimeException('oups', 500));
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-error.log';
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('RuntimeException', $content);
        $this->assertStringContainsString('oups', $content);
    }

    public function testErrorWritesFileAndLine(): void
    {
        Log::error(new \InvalidArgumentException('mauvais argument'));
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-error.log';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Stack trace', $content);
    }

    // ── job ──────────────────────────────────────────────────────────────���────

    public function testJobWritesStatusAndName(): void
    {
        Log::job('App\Job\SendMail', 'completed');
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-jobs.log';
        $this->assertFileExists($logFile);
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('COMPLETED', $content);
        $this->assertStringContainsString('App\Job\SendMail', $content);
    }

    public function testJobWithDetailsIncludesDetails(): void
    {
        Log::job('App\Job\SendMail', 'failed', 'SMTP timeout');
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-jobs.log';
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('SMTP timeout', $content);
    }

    // ── debug ───────────────────���────────────────────────────��────────────────

    public function testDebugIsNoopWhenConstantNotDefined(): void
    {
        // DEBUG n'est pas défini dans le bootstrap de test
        Log::debug('message silencieux');
        $logFile = $this->logDir . '/' . date('Y-m-d') . '-debug.log';
        $this->assertFileDoesNotExist($logFile);
    }

    // ── cleanup ───────────────────────���───────────────────────────────────────

    public function testCleanupDeletesOldFiles(): void
    {
        // Crée un faux fichier log avec une date de modification ancienne
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0775, true);
        }
        $oldFile = $this->logDir . '/2000-01-01-skankydev.log';
        file_put_contents($oldFile, 'vieux log');
        touch($oldFile, strtotime('-30 days'));

        Log::cleanup(10);

        $this->assertFileDoesNotExist($oldFile);
    }

    public function testCleanupKeepsRecentFiles(): void
    {
        Log::info('récent');
        $recentFile = $this->todayLog();

        Log::cleanup(10);

        $this->assertFileExists($recentFile);
    }
}
