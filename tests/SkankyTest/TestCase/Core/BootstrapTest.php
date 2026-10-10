<?php

namespace SkankyTest\TestCase\Core;

use PHPUnit\Framework\TestCase;
use SkankyDev\Core\Bootstrap;

class BootstrapTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void {
        $this->envFile = tempnam(sys_get_temp_dir(), 'env');
    }

    protected function tearDown(): void {
        @unlink($this->envFile);
        foreach (['SKANKY_TEST_A', 'SKANKY_TEST_B', 'SKANKY_TEST_EMPTY'] as $key) {
            unset($_ENV[$key]);
            putenv($key);
        }
    }

    public function testLoadEnv(): void {
        file_put_contents($this->envFile, "# commentaire\nSKANKY_TEST_A=valeur\n SKANKY_TEST_B = avec=egal \nSKANKY_TEST_EMPTY=\n");
        Bootstrap::loadEnv($this->envFile);

        $this->assertEquals('valeur', getenv('SKANKY_TEST_A'));
        $this->assertEquals('valeur', $_ENV['SKANKY_TEST_A']);
        $this->assertEquals('avec=egal', getenv('SKANKY_TEST_B'));
        $this->assertEquals('', $_ENV['SKANKY_TEST_EMPTY']);
    }

    public function testLoadEnvMissingFileDoesNothing(): void {
        Bootstrap::loadEnv($this->envFile . '.absent');
        $this->assertFalse(getenv('SKANKY_TEST_A'));
    }
}
