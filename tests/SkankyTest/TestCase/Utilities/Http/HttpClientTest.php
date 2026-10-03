<?php

namespace SkankyTest\TestCase\Utilities\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Http\HttpClient;
use SkankyDev\Utilities\Http\HttpResult;

class HttpClientTest extends TestCase
{
    /** Lit une propriété protected de l'instance. */
    private function prop(HttpClient $client, string $name): mixed {
        $ref = new \ReflectionProperty(HttpClient::class, $name);
        return $ref->getValue($client);
    }

    public function testWithHeaderIsFluentAndStores(): void {
        $client = new HttpClient();
        $this->assertSame($client, $client->withHeader('X-Foo', 'bar'));
        $this->assertSame(['X-Foo' => 'bar'], $this->prop($client, 'headers'));
    }

    public function testWithHeaderOverrides(): void {
        $client = (new HttpClient())
            ->withHeader('X-Foo', 'bar')
            ->withHeader('X-Foo', 'baz');
        $this->assertSame(['X-Foo' => 'baz'], $this->prop($client, 'headers'));
    }

    public function testWithHeadersMerges(): void {
        $client = (new HttpClient())
            ->withHeader('X-Foo', 'bar')
            ->withHeaders(['X-Bar' => '1', 'X-Baz' => '2']);
        $this->assertSame(
            ['X-Foo' => 'bar', 'X-Bar' => '1', 'X-Baz' => '2'],
            $this->prop($client, 'headers')
        );
    }

    public function testTimeoutIsFluentAndStores(): void {
        $client = new HttpClient();
        $this->assertSame($client, $client->timeout(60));
        $this->assertSame(60, $this->prop($client, 'timeout'));
    }

    public function testFormatHeadersBuildsLines(): void {
        $client = new HttpClient();
        $ref = new \ReflectionMethod(HttpClient::class, 'formatHeaders');
        $lines = $ref->invoke($client, ['Content-Type' => 'application/json', 'X-CSRF-Token' => 'abc']);
        $this->assertSame(['Content-Type: application/json', 'X-CSRF-Token: abc'], $lines);
    }

    public function testNetworkFailureReturnsResultWithError(): void {
        // Port 1 sur le loopback : connexion refusée immédiate, déterministe et hors-ligne.
        $res = (new HttpClient())->timeout(2)->get('http://127.0.0.1:1');

        $this->assertInstanceOf(HttpResult::class, $res);
        $this->assertSame(0, $res->status());
        $this->assertFalse($res->ok());
        $this->assertTrue($res->failed());
        $this->assertNotEquals('', $res->error());
    }
}
