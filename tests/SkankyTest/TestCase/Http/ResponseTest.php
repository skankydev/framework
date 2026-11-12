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

    // ── file ─────────────────────────────────────────────────────────────────

    private function tmpFile(string $content): string {
        $path = tempnam(sys_get_temp_dir(), 'resp');
        file_put_contents($path, $content);
        return $path;
    }

    public function testFileFromStoredFileIsInlineWithItsMetadata(): void {
        $path = $this->tmpFile('%PDF-1.4');
        $stored = new \SkankyDev\Model\Document\StoredFile(['path' => 'x.pdf', 'mime' => 'application/pdf', 'original_name' => 'Facture été.pdf']);
        $folder = \SkankyDev\Config\Config::get('upload.folder');
        \SkankyDev\Config\Config::set('upload.folder', dirname($path));
        $stored->path = basename($path);
        try {
            $headers = Response::file($stored)->getHeaders();
        } finally {
            \SkankyDev\Config\Config::set('upload.folder', $folder);
            unlink($path);
        }
        $this->assertEquals('application/pdf', $headers['Content-Type']);
        $this->assertEquals('8', $headers['Content-Length']);
        $this->assertEquals("inline; filename=\"Facture __t__.pdf\"; filename*=UTF-8''Facture%20%C3%A9t%C3%A9.pdf", $headers['Content-Disposition']);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testFileCanForceDownloadAndRename(): void {
        $path = $this->tmpFile('hello');
        $headers = Response::file($path, 'notes.txt', download: true)->getHeaders();
        unlink($path);
        $this->assertStringStartsWith('attachment; filename="notes.txt"', $headers['Content-Disposition']);
        $this->assertEquals('text/plain', $headers['Content-Type']);
    }

    public function testFileNeverShowsHtmlInline(): void {
        $path = $this->tmpFile('<html><body><script>alert(1)</script></body></html>');
        $headers = Response::file($path)->getHeaders();
        unlink($path);
        $this->assertStringStartsWith('attachment;', $headers['Content-Disposition']);
    }

    public function testFileSendStreamsTheContent(): void {
        $path = $this->tmpFile('contenu du fichier');
        ob_start();
        Response::file($path)->send();
        $out = ob_get_clean();
        unlink($path);
        $this->assertEquals('contenu du fichier', $out);
    }

    public function testFileMissingThrowsNotFound(): void {
        $this->expectException(\SkankyDev\Exception\NotFoundException::class);
        $this->expectExceptionCode(404);
        Response::file(sys_get_temp_dir() . DS . 'nope_' . bin2hex(random_bytes(4)));
    }
}
