<?php

namespace SkankyTest\TestCase\Http;

use PHPUnit\Framework\TestCase;
use SkankyDev\Http\Response;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;

class ResponseTest extends TestCase
{
    protected function setUp(): void {
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);

        $_SESSION = [];
        $_GET = $_POST = $_COOKIE = $_FILES = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST'      => 'skankyblog.local',
            'REQUEST_URI'    => '/module/index',
            'REMOTE_ADDR'    => '127.0.0.1',
        ];

        Router::_findCurrentRoute('/module/index');
    }

    // ── constructor / fluent interface ────────────────────────────────────────

    public function testInstantiates(): void {
        $response = new Response('module.index');
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testStatusReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->status(404));
    }

    public function testHeaderReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->header('X-Foo', 'bar'));
    }

    public function testViewNameReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->viewName('module.index'));
    }

    // ── withErrors / withInput ────────────────────────────────────────────────

    public function testWithErrorsStoresInSession(): void {
        $response = new Response();
        $response->withErrors(['name' => ['required']]);
        $this->assertEquals(['name' => ['required']], $_SESSION['errors']);
    }

    public function testWithErrorsReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->withErrors([]));
    }

    public function testWithInputStoresInSession(): void {
        $response = new Response();
        $response->withInput(['name' => 'Simon']);
        $this->assertEquals(['name' => 'Simon'], $_SESSION['old']);
    }

    public function testWithInputReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->withInput([]));
    }

    // ── withFlash ─────────────────────────────────────────────────────────────

    public function testWithFlashStoresInSession(): void {
        $response = new Response();
        $response->withFlash('success', 'Saved!');
        $this->assertEquals('success', $_SESSION['flash'][0]['type']);
        $this->assertEquals('Saved!',  $_SESSION['flash'][0]['message']);
    }

    public function testWithFlashReturnsSelf(): void {
        $response = new Response();
        $this->assertSame($response, $response->withFlash('info', 'hello'));
    }

    // ── build() ───────────────────────────────────────────────────────────────

    public function testBuildJsonResponseWhenClientWantsJson(): void {
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = new Response('', ['key' => 'value']);
        $response->build();

        $ref2 = new \ReflectionProperty($response, 'body');
        $body = $ref2->getValue($response);

        $this->assertJson($body);
        $this->assertStringContainsString('value', $body);
    }

    public function testBuildHtmlResponseRendersView(): void {
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $response = new Response('_test.index', ['title' => 'Test Build']);
        $response->build();

        $ref2 = new \ReflectionProperty($response, 'body');
        $body = $ref2->getValue($response);

        $this->assertStringContainsString('Test Build', $body);
    }

    public function testBuildJsonWhenNoViewNameEvenIfClientWantsHtml(): void {
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        // response() crée un Response sans vue → réponse de données → toujours du JSON,
        // même si le client n'a pas demandé application/json.
        $response = new Response('', ['ok' => true, 'label' => 'Terminé']);
        $response->build();

        $ref2 = new \ReflectionProperty($response, 'body');
        $body = $ref2->getValue($response);

        $this->assertJson($body);
        $this->assertStringContainsString('ok', $body);
    }

    public function testBuildReturnsSelf(): void {
        $ref = new \ReflectionProperty(Request::class, '_instance');
        $ref->setValue(null, null);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = new Response('', []);
        $this->assertSame($response, $response->build());
    }

    // ── chaining ─────────────────────────────────────────────────────────────

    public function testFluentChainingWorks(): void {
        $response = (new Response())
            ->status(302)
            ->header('Location', '/module')
            ->withErrors(['field' => ['error']])
            ->withInput(['field' => 'val']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(['field' => ['error']], $_SESSION['errors']);
        $this->assertEquals(['field' => 'val'], $_SESSION['old']);
    }
}
