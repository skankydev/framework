<?php

namespace SkankyTest\TestCase\Utilities\Http;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SkankyDev\Utilities\Http\HttpResult;

class HttpResultTest extends TestCase
{
    public function testStatusReturnsCode(): void {
        $res = new HttpResult(204);
        $this->assertEquals(204, $res->status());
    }

    #[DataProvider('okProvider')]
    public function testOk(int $status, bool $expected): void {
        $res = new HttpResult($status);
        $this->assertSame($expected, $res->ok());
    }

    public static function okProvider(): array {
        return [
            '200 ok'           => [200, true],
            '201 ok'           => [201, true],
            '299 ok'           => [299, true],
            '300 redirect'     => [300, false],
            '404 not found'    => [404, false],
            '500 server error' => [500, false],
            '0 no response'    => [0,   false],
        ];
    }

    public function testFailedOnServerError(): void {
        $this->assertTrue((new HttpResult(500))->failed());
        $this->assertTrue((new HttpResult(404))->failed());
    }

    public function testFailedOnTransportError(): void {
        $res = new HttpResult(0, '', [], 'Connection refused');
        $this->assertTrue($res->failed());
    }

    public function testNotFailedOnSuccess(): void {
        $this->assertFalse((new HttpResult(200, 'ok'))->failed());
    }

    public function testBodyReturnsRaw(): void {
        $res = new HttpResult(200, '{"a":1}');
        $this->assertEquals('{"a":1}', $res->body());
    }

    public function testJsonDecodesAssocByDefault(): void {
        $res = new HttpResult(200, '{"name":"penelope","tools":["a","b"]}');
        $this->assertSame(['name' => 'penelope', 'tools' => ['a', 'b']], $res->json());
    }

    public function testJsonDecodesObjectWhenAssocFalse(): void {
        $res = new HttpResult(200, '{"name":"penelope"}');
        $decoded = $res->json(false);
        $this->assertIsObject($decoded);
        $this->assertEquals('penelope', $decoded->name);
    }

    public function testJsonReturnsNullOnInvalid(): void {
        $res = new HttpResult(200, 'pas du json');
        $this->assertNull($res->json());
    }

    public function testHeaderIsCaseInsensitive(): void {
        $res = new HttpResult(200, '', ['content-type' => 'application/json']);
        $this->assertEquals('application/json', $res->header('Content-Type'));
        $this->assertEquals('application/json', $res->header('content-type'));
    }

    public function testHeaderReturnsNullWhenAbsent(): void {
        $res = new HttpResult(200, '', ['content-type' => 'application/json']);
        $this->assertNull($res->header('X-Absent'));
    }

    public function testHeadersReturnsAll(): void {
        $headers = ['content-type' => 'application/json', 'x-foo' => 'bar'];
        $res = new HttpResult(200, '', $headers);
        $this->assertSame($headers, $res->headers());
    }

    public function testErrorReturnsMessage(): void {
        $res = new HttpResult(0, '', [], 'timeout');
        $this->assertEquals('timeout', $res->error());
        $this->assertEquals('', (new HttpResult(200, 'ok'))->error());
    }
}
