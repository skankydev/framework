<?php

namespace SkankyTest\TestCase\Model;

use DateTime;
use PHPUnit\Framework\TestCase;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use SkankyDev\Model\Document\EmbeddedDocument;

enum FixtureColor: string {
	case RED  = 'red';
	case BLUE = 'blue';
}

class FixtureEmbedded extends EmbeddedDocument {
	public string $label = '';
	public int $count = 0;
	public ?FixtureColor $color = null;
	public ?DateTime $when = null;
	public ?ObjectId $ref = null;
}

class EmbeddedDocumentTest extends TestCase
{
    public function testFillCastsByDeclaredType(): void {
        $hex = '5099803df3f4948bd2f98391';
        $doc = new FixtureEmbedded([
            'label' => 'hello',
            'count' => 3,
            'color' => 'blue',
            'when'  => '2026-06-24T10:30',
            'ref'   => $hex,
        ]);

        $this->assertSame('hello', $doc->label);
        $this->assertSame(3, $doc->count);
        $this->assertSame(FixtureColor::BLUE, $doc->color);
        $this->assertInstanceOf(DateTime::class, $doc->when);
        $this->assertInstanceOf(ObjectId::class, $doc->ref);
        $this->assertSame($hex, (string) $doc->ref);
    }

    public function testFillKeepsDefaultOnInvalidEnum(): void {
        $doc = new FixtureEmbedded(['color' => 'not-a-color']);
        $this->assertNull($doc->color);
    }

    public function testBsonSerializeConvertsTypes(): void {
        $doc = new FixtureEmbedded([
            'label' => 'x',
            'color' => 'red',
            'when'  => '2026-01-01T00:00',
        ]);
        $bson = $doc->bsonSerialize();

        $this->assertSame('red', $bson['color']);              // enum → value
        $this->assertInstanceOf(UTCDateTime::class, $bson['when']); // DateTime → UTCDateTime
    }

    public function testBsonRoundTrip(): void {
        $doc = new FixtureEmbedded(['label' => 'rt', 'count' => 7, 'color' => 'blue']);
        $bson = $doc->bsonSerialize();

        $restored = new FixtureEmbedded();
        $restored->bsonUnserialize($bson + ['__pclass' => 'ignored']);

        $this->assertSame('rt', $restored->label);
        $this->assertSame(7, $restored->count);
        $this->assertSame(FixtureColor::BLUE, $restored->color); // value re-cast en enum
    }

    public function testBsonUnserializeConvertsUtcToAppDefaultTimezone(): void {
        // UTCDateTime::toDateTime() renvoie toujours de l'UTC ; on doit le
        // reconvertir vers date_default_timezone_get() pour l'affichage.
        $original = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $doc = new FixtureEmbedded();
            $doc->bsonUnserialize([
                '__pclass' => 'ignored',
                // 10h UTC en janvier = 11h Europe/Paris (pas de DST)
                'when'     => new UTCDateTime(new DateTime('2025-01-15 10:00:00', new \DateTimeZone('UTC'))),
            ]);

            $this->assertSame('Europe/Paris', $doc->when->getTimezone()->getName());
            $this->assertSame('2025-01-15 11:00:00', $doc->when->format('Y-m-d H:i:s'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testJsonSerializeConvertsObjectIdAndEnum(): void {
        $hex = '5099803df3f4948bd2f98391';
        $doc = new FixtureEmbedded(['color' => 'red', 'ref' => $hex]);
        $json = $doc->jsonSerialize();

        $this->assertSame('red', $json['color']);
        $this->assertSame($hex, $json['ref']);
        $this->assertIsString($json['ref']);
    }
}
