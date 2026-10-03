<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\Paginator;
use SkankyDev\Http\Request;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;

class PaginatorTest extends TestCase
{
    private array $data;
    private array $option;

    protected function setUp(): void {
        $this->data = range(0, 10);
        $this->option = [
            'limit' => 10,
            'page'  => 1,
            'total' => 184,
            'range' => 5,
            'sort'  => ['slug' => 1],
        ];
    }

    public function testGetOptionComputesPaginationInfo(): void {
        $paginator = new Paginator($this->data, $this->option);
        $result    = $paginator->getOption();

        $this->assertEquals(19, $result['pages']);
        $this->assertEquals(1,  $result['first']);
        $this->assertEquals(19, $result['last']);
        $this->assertEquals(2,  $result['next']);
        $this->assertEquals(1,  $result['prev']);
        $this->assertEquals(1,  $result['start']);
        $this->assertEquals(4,  $result['stop']);
        // getOption() always attaches link and get keys
        $this->assertArrayHasKey('link', $result);
        $this->assertArrayHasKey('get',  $result);
    }

    public function testGetOptionAttachesLinkAndGet(): void {
        $paginator = new Paginator($this->data, $this->option);
        $link = ['controller' => 'Module', 'action' => 'index'];
        $get  = ['search' => 'test'];
        $result = $paginator->getOption($link, $get);

        $this->assertEquals($link, $result['link']);
        $this->assertEquals($get,  $result['get']);
    }

    public function testSortParamsTogglesOrder(): void {
        $paginator = new Paginator($this->data, $this->option);

        // 'slug' is the current sort field (order 1) → clicking it inverts to -1
        $this->assertEquals(['page' => 1, 'field' => 'slug', 'order' => -1], $paginator->sortParams('slug'));

        // 'title' is not sorted → clicking it sets order to 1
        $this->assertEquals(['page' => 1, 'field' => 'title', 'order' => 1], $paginator->sortParams('title'));
    }

    // ── sortGet : tri courant exposé pour les liens de page ─────────────────────

    public function testSortGetReturnsCurrentSort(): void {
        $paginator = new Paginator($this->data, $this->option); // sort = ['slug' => 1]
        $this->assertEquals(['field' => 'slug', 'order' => 1], $paginator->sortGet());
    }

    public function testSortGetIsEmptyForDefaultIdSort(): void {
        // Le tri stable par défaut (_id) ne doit pas être exposé dans l'URL
        $paginator = new Paginator($this->data, ['limit' => 10, 'page' => 1, 'total' => 1, 'range' => 5, 'sort' => ['_id' => -1]]);
        $this->assertEquals([], $paginator->sortGet());
    }

    // ── sortLink : lien d'en-tête prêt à afficher ───────────────────────────────

    public function testSortLinkTogglesAndMarksActiveColumn(): void {
        $this->setUpRoute();
        $paginator = new Paginator($this->data, $this->option); // sort = ['slug' => 1]

        $html = $paginator->sortLink('slug', 'Slug');

        // toggle : slug est trié asc → le lien doit demander desc
        $this->assertStringContainsString('field=slug', $html);
        $this->assertStringContainsString('order=-1', $html);
        $this->assertStringContainsString('page=1', $html);
        // colonne active → classe + flèche montante (tri courant asc)
        $this->assertStringContainsString('sorted', $html);
        $this->assertStringContainsString('&#9650;', $html);
    }

    public function testSortLinkOnInactiveColumnHasNoMarker(): void {
        $this->setUpRoute();
        $paginator = new Paginator($this->data, $this->option);

        $html = $paginator->sortLink('title', 'Title');

        $this->assertStringContainsString('field=title', $html);
        $this->assertStringContainsString('order=1', $html);
        $this->assertStringNotContainsString('sorted', $html);
    }

    // ── Table data-driven : valeurs de cellule auto-formatées ───────────────────

    public function testCellValueFormatsByRuntimeType(): void {
        $paginator = new Paginator($this->data, $this->option);

        $doc = (object) [
            'name'    => 'Salon',
            'count'   => ['a', 'b', 'c'],
            'when'    => new \DateTime('2026-06-15 14:30'),
            'flagOn'  => true,
            'flagOff' => false,
            'empty'   => null,
        ];

        $this->assertSame('Salon', $paginator->cellValue($doc, 'name'));
        $this->assertSame('3', $paginator->cellValue($doc, 'count'));
        $this->assertSame('15/06/2026 14:30', $paginator->cellValue($doc, 'when'));
        $this->assertStringContainsString('icon-check', $paginator->cellValue($doc, 'flagOn'));
        $this->assertStringContainsString('icon-x', $paginator->cellValue($doc, 'flagOff'));
        $this->assertSame('', $paginator->cellValue($doc, 'empty'));
    }

    public function testCellValueEscapesStrings(): void {
        $paginator = new Paginator($this->data, $this->option);
        $doc = (object) ['name' => '<script>alert(1)</script>'];

        $value = $paginator->cellValue($doc, 'name');
        $this->assertStringNotContainsString('<script>', $value);
        $this->assertStringContainsString('&lt;script&gt;', $value);
    }

    public function testResourceControllerAndSingularFromDocumentClass(): void {
        $paginator = new Paginator($this->data, $this->option);
        $paginator->setDocumentClass('App\\Model\\Document\\Module');

        $this->assertSame('Module', $paginator->controller());
        $this->assertSame('module', $paginator->singular());
    }

    public function testDisplayFieldRoundTrip(): void {
        $paginator = new Paginator($this->data, $this->option);
        $fields = ['name' => ['label' => 'Name', 'sort' => true]];
        $paginator->setDisplayField($fields);

        $this->assertSame($fields, $paginator->getDisplayField());
    }

    /** Met en place une route courante pour que UrlBuilder puisse construire les liens. */
    private function setUpRoute(): void {
        (new \ReflectionProperty(Router::class,     '_instance'))->setValue(null, null);
        (new \ReflectionProperty(Request::class,    '_instance'))->setValue(null, null);
        (new \ReflectionProperty(UrlBuilder::class, '_instance'))->setValue(null, null);

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

    public function testLastPageBoundary(): void {
        $paginator = new Paginator($this->data, [
            'limit' => 10,
            'page'  => 19,
            'total' => 184,
            'range' => 5,
            'sort'  => ['slug' => 1],
        ]);
        $result = $paginator->getOption();

        $this->assertEquals(19, $result['next']); // already on last page
        $this->assertEquals(18, $result['prev']);
    }

    public function testIterable(): void {
        $paginator = new Paginator($this->data, $this->option);
        $count = 0;
        foreach ($paginator as $item) {
            $count++;
        }
        $this->assertEquals(count($this->data), $count);
    }
}
