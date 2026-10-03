<?php

namespace SkankyTest\TestCase\Model;

use DateTime;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;
use SkankyDev\Model\Document\EmbeddedDocument;
use SkankyDev\Model\Document\MasterDocument;

// ── Fixtures ──────────────────────────────────────────────────────────────────

enum FixtureStatus: string
{
    case A = 'a';
    case B = 'b';
}

#[\AllowDynamicProperties]
class DocFixture extends MasterDocument
{
    public string    $name       = '';
    public int       $value      = 0;
    public ?DateTime $created_at = null;
}

class DocChild extends EmbeddedDocument
{
    public string $label = '';
}

class DocWithChild extends MasterDocument
{
    public ?DocChild $child = null;
}

#[\AllowDynamicProperties]
class DocWithRelation extends MasterDocument
{
    public string   $name = '';
    public ObjectId $module_id; // FK typée ObjectId (convention SkankyDev)
}

#[\AllowDynamicProperties]
class DocWithTypes extends MasterDocument
{
    public string        $title  = '';
    public FixtureStatus $status = FixtureStatus::A;
    public DateTime      $due;
}

#[\AllowDynamicProperties]
class DocWithRelationGetter extends MasterDocument
{
    public ObjectId $module_id;

    // Reproduit le pattern généré par le CrudMaker : garde isset() puis résolution.
    // Ici on renvoie un stand-in au lieu d'appeler une Collection (pas de DB en unitaire).
    public function getModule(): ?object
    {
        if (!isset($this->module_id)) {
            return null;
        }
        return (object) ['id' => (string) $this->module_id];
    }
}

#[\AllowDynamicProperties]
class DocWithGetter extends MasterDocument
{
    public string $first_name = '';
    public string $last_name  = '';

    public function getFullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class MasterDocumentTest extends TestCase
{
    // ── __construct / fill ────────────────────────────────────────────────────

    public function testConstructWithDataCallsFill(): void
    {
        $doc = new DocFixture(['name' => 'Simon', 'value' => 7]);
        $this->assertEquals('Simon', $doc->name);
        $this->assertEquals(7, $doc->value);
    }

    public function testConstructEmptyLeavesDefaults(): void
    {
        $doc = new DocFixture();
        $this->assertEquals('', $doc->name);
        $this->assertEquals(0,  $doc->value);
    }

    public function testFillSetsProperties(): void
    {
        $doc = new DocFixture();
        $doc->fill(['name' => 'test', 'value' => 99]);
        $this->assertEquals('test', $doc->name);
        $this->assertEquals(99,     $doc->value);
    }

    public function testFillIgnoresUnknownKeys(): void
    {
        $doc = new DocFixture();
        $doc->fill(['unknown_key' => 'ignored']);
        $this->assertFalse(isset($doc->unknown_key));
    }

    public function testFillReturnsSelf(): void
    {
        $doc = new DocFixture();
        $this->assertSame($doc, $doc->fill([]));
    }

    public function testFillDoesNotMassAssignId(): void
    {
        $doc = new DocFixture(['_id' => (string) new ObjectId(), 'name' => 'x']);
        // _id n'est jamais remplissable via fill (sécurité), il reste null.
        $this->assertNull($doc->_id);
    }

    // ── collectionName ────────────────────────────────────────────────────────

    public function testCollectionNameDerivedFromClass(): void
    {
        $name = DocFixture::collectionName();
        $this->assertStringEndsWith('DocFixtureCollection', $name);
        $this->assertStringNotContainsString('Document', $name);
    }

    // ── __get ─────────────────────────────────────────────────────────────────

    public function testMagicGetExistingProperty(): void
    {
        $doc       = new DocFixture();
        $doc->name = 'hello';
        $this->assertEquals('hello', $doc->name);
    }

    public function testMagicGetNonExistentPropertyReturnsNull(): void
    {
        $doc = new DocFixture();
        $this->assertNull($doc->nonExistentProperty);
    }

    public function testMagicGetCallsGetterMethod(): void
    {
        $doc             = new DocWithGetter();
        $doc->first_name = 'Simon';
        $doc->last_name  = 'S';
        $this->assertEquals('Simon S', $doc->full_name);
    }

    // ── fill : ObjectId (par type, pas par nom) ─────────────────────────────────

    public function testFillCastsStringToObjectIdForTypedProperty(): void
    {
        $id  = (string) new ObjectId();
        $doc = new DocWithRelation(['name' => 'test', 'module_id' => $id]);

        $this->assertInstanceOf(ObjectId::class, $doc->module_id);
        $this->assertEquals($id, (string) $doc->module_id);
    }

    public function testFillAcceptsObjectIdInstanceDirectly(): void
    {
        $oid = new ObjectId();
        $doc = new DocWithRelation(['module_id' => $oid]);

        $this->assertSame($oid, $doc->module_id);
    }

    public function testFillLeavesObjectIdPropertyUnsetWhenEmpty(): void
    {
        // Nouveau comportement : une FK vide n'est PAS auto-générée, on laisse le défaut (ici unset).
        $doc = new DocWithRelation(['name' => 'test', 'module_id' => '']);
        $this->assertFalse(isset($doc->module_id));
    }

    // ── fill : BackedEnum ───────────────────────────────────────────────────────

    public function testFillCastsStringToBackedEnum(): void
    {
        $doc = new DocWithTypes(['status' => 'b']);
        $this->assertSame(FixtureStatus::B, $doc->status);
    }

    public function testFillKeepsDefaultForInvalidEnumValue(): void
    {
        $doc = new DocWithTypes(['status' => 'nope']);
        $this->assertSame(FixtureStatus::A, $doc->status); // tryFrom null → défaut conservé
    }

    public function testFillAcceptsEnumInstanceDirectly(): void
    {
        $doc = new DocWithTypes(['status' => FixtureStatus::B]);
        $this->assertSame(FixtureStatus::B, $doc->status);
    }

    // ── fill : DateTime ─────────────────────────────────────────────────────────

    public function testFillCastsDatetimeLocalStringToDateTime(): void
    {
        $doc = new DocWithTypes(['due' => '2026-06-20T14:30']);
        $this->assertInstanceOf(DateTime::class, $doc->due);
        $this->assertEquals('2026-06-20 14:30', $doc->due->format('Y-m-d H:i'));
    }

    public function testFillCastsDateStringToDateTime(): void
    {
        $doc = new DocWithTypes(['due' => '2026-06-20']);
        $this->assertEquals('2026-06-20 00:00', $doc->due->format('Y-m-d H:i'));
    }

    public function testFillLeavesDateTimeUnsetWhenEmpty(): void
    {
        $doc = new DocWithTypes(['due' => '']);
        $this->assertFalse(isset($doc->due));
    }

    // ── relation accessor (pattern getXxx généré) ──────────────────────────────

    public function testRelationGetterReturnsNullWhenFkUnset(): void
    {
        $doc = new DocWithRelationGetter();
        $this->assertNull($doc->module); // __get → getModule() → garde isset → null
    }

    public function testRelationGetterResolvesWhenFkSet(): void
    {
        $doc            = new DocWithRelationGetter();
        $doc->module_id = new ObjectId();
        $this->assertNotNull($doc->module);
    }

    // ── bsonSerialize ─────────────────────────────────────────────────────────

    public function testBsonSerializeReturnsArray(): void
    {
        $doc        = new DocFixture();
        $doc->name  = 'test';
        $doc->value = 5;

        $result = $doc->bsonSerialize();
        $this->assertIsArray($result);
        $this->assertEquals('test', $result['name']);
        $this->assertEquals(5,      $result['value']);
    }

    public function testBsonSerializeConvertsDateTimeToUtcDateTime(): void
    {
        $doc             = new DocFixture();
        $doc->created_at = new DateTime('2025-01-01');

        $result = $doc->bsonSerialize();
        $this->assertInstanceOf(UTCDateTime::class, $result['created_at']);
    }

    public function testBsonSerializeConvertsEnumToScalarValue(): void
    {
        $doc    = new DocWithTypes(['status' => 'b']);
        $result = $doc->bsonSerialize();
        $this->assertSame('b', $result['status']);
    }

    public function testBsonSerializeKeepsObjectIdForFkProperty(): void
    {
        $id     = (string) new ObjectId();
        $doc    = new DocWithRelation(['name' => 'test', 'module_id' => $id]);
        $result = $doc->bsonSerialize();

        $this->assertInstanceOf(ObjectId::class, $result['module_id']);
        $this->assertEquals($id, (string) $result['module_id']);
    }

    public function testBsonSerializeOmitsUnsetFkProperty(): void
    {
        // module_id non fourni → propriété typée non initialisée → absente du payload.
        $doc    = new DocWithRelation(['name' => 'test']);
        $result = $doc->bsonSerialize();
        $this->assertArrayNotHasKey('module_id', $result);
    }

    public function testBsonSerializeDropsEmptyId(): void
    {
        // _id null (doc neuf) → retiré pour laisser MongoDB générer l'_id nativement.
        $doc    = new DocFixture(['name' => 'x']);
        $result = $doc->bsonSerialize();
        $this->assertArrayNotHasKey('_id', $result);
    }

    public function testBsonSerializeKeepsSetId(): void
    {
        $doc      = new DocFixture(['name' => 'x']);
        $doc->_id = new ObjectId();
        $result   = $doc->bsonSerialize();

        $this->assertArrayHasKey('_id', $result);
        $this->assertInstanceOf(ObjectId::class, $result['_id']);
    }

    // ── bsonUnserialize ───────────────────────────────────────────────────────

    public function testBsonUnserializeSetsProperties(): void
    {
        $doc = new DocFixture();
        $doc->bsonUnserialize(['__pclass' => 'X', 'name' => 'foo', 'value' => 3]);

        $this->assertEquals('foo', $doc->name);
        $this->assertEquals(3,     $doc->value);
    }

    public function testBsonUnserializeConvertsUtcToDateTime(): void
    {
        $doc = new DocFixture();
        $doc->bsonUnserialize([
            '__pclass'   => 'X',
            'name'       => '',
            'value'      => 0,
            'created_at' => new UTCDateTime(new DateTime('2025-06-01')),
        ]);

        $this->assertInstanceOf(DateTime::class, $doc->created_at);
    }

    public function testBsonUnserializeConvertsUtcToAppDefaultTimezone(): void
    {
        // UTCDateTime::toDateTime() renvoie toujours de l'UTC ; on doit le
        // reconvertir vers date_default_timezone_get() pour l'affichage.
        $original = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $doc = new DocFixture();
            $doc->bsonUnserialize([
                '__pclass'   => 'X',
                'name'       => '',
                'value'      => 0,
                // 10h UTC en janvier = 11h Europe/Paris (pas de DST)
                'created_at' => new UTCDateTime(new DateTime('2025-01-15 10:00:00', new \DateTimeZone('UTC'))),
            ]);

            $this->assertSame('Europe/Paris', $doc->created_at->getTimezone()->getName());
            $this->assertSame('2025-01-15 11:00:00', $doc->created_at->format('Y-m-d H:i:s'));
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testBsonUnserializeCastsStringToEnum(): void
    {
        $doc = new DocWithTypes();
        $doc->bsonUnserialize(['__pclass' => 'X', 'status' => 'b']);
        $this->assertSame(FixtureStatus::B, $doc->status);
    }

    // ── jsonSerialize ─────────────────────────────────────────────────────────

    public function testJsonSerializeReturnsArray(): void
    {
        $doc        = new DocFixture();
        $doc->name  = 'json';
        $doc->value = 42;

        $result = $doc->jsonSerialize();
        $this->assertIsArray($result);
        $this->assertEquals('json', $result['name']);
    }

    public function testJsonSerializeIsJsonEncodable(): void
    {
        $doc       = new DocFixture(['name' => 'encodable', 'value' => 1]);
        $json      = json_encode($doc);
        $this->assertJson($json);
        $this->assertStringContainsString('encodable', $json);
    }

    public function testJsonSerializeConvertsObjectIdToString(): void
    {
        $doc      = new DocFixture();
        $doc->_id = new ObjectId();

        $result = $doc->jsonSerialize();
        $this->assertIsString($result['_id']);
    }

    public function testJsonSerializeConvertsEnumToScalarValue(): void
    {
        $doc    = new DocWithTypes(['status' => 'b']);
        $result = $doc->jsonSerialize();
        $this->assertSame('b', $result['status']);
    }

    // ── find() ────────────────────────────────────────────────────────────────

    public function testFindThrowsWhenCollectionDoesNotExist(): void
    {
        // DocFixture::collectionName() → something like '...DocFixtureCollection' which doesn't exist
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(404);
        DocFixture::find('507f1f77bcf86cd799439011');
    }

    // ── Dirty tracking ────────────────────────────────────────────────────────

    /** Simule une relecture Mongo (bsonUnserialize, sans passer par le constructeur). */
    private function loaded(array $data): DocFixture
    {
        $doc = (new \ReflectionClass(DocFixture::class))->newInstanceWithoutConstructor();
        $doc->bsonUnserialize($data);
        return $doc;
    }

    public function testNewDocumentHasNoOriginalAndEverythingIsDirty(): void
    {
        $doc = new DocFixture(['name' => 'a']);
        $this->assertFalse($doc->hasOriginal());
        $this->assertTrue($doc->isDirty());
        $this->assertContains('name', $doc->getDirty());
        $this->assertContains('value', $doc->getDirty());
    }

    public function testLoadedDocumentIsClean(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a', 'value' => 1, 'created_at' => new UTCDateTime()]);
        $this->assertTrue($doc->hasOriginal());
        $this->assertFalse($doc->isDirty());
        $this->assertSame([], $doc->getDirty());
    }

    public function testModifiedFieldIsDirty(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a', 'value' => 1]);
        $doc->name = 'b';
        $this->assertSame(['name'], $doc->getDirty());
        $this->assertTrue($doc->isDirty('name'));
        $this->assertFalse($doc->isDirty('value'));
    }

    public function testSameValueReassignedIsNotDirty(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a', 'created_at' => new UTCDateTime(1700000000000)]);
        $doc->name = 'a';
        $doc->created_at = (new UTCDateTime(1700000000000))->toDateTime();
        $this->assertFalse($doc->isDirty());
    }

    public function testDirtyDataReturnsOnlySerializedModifiedFields(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a', 'value' => 1]);
        $doc->created_at = new DateTime('2026-01-01');
        $data = $doc->dirtyData();
        $this->assertSame(['created_at'], array_keys($data));
        $this->assertInstanceOf(UTCDateTime::class, $data['created_at']);
    }

    public function testDirtyDataNeverContainsId(): void
    {
        $doc = new DocFixture(['name' => 'a']);
        $doc->_id = new ObjectId();
        $this->assertArrayNotHasKey('_id', $doc->dirtyData());
        $this->assertArrayHasKey('name', $doc->dirtyData());
    }

    public function testSyncOriginalMakesDocumentClean(): void
    {
        $doc = new DocFixture(['name' => 'a']);
        $doc->syncOriginal();
        $this->assertFalse($doc->isDirty());
        $doc->value = 42;
        $this->assertSame(['value'], $doc->getDirty());
    }

    public function testGetOriginalReturnsValueBeforeModification(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a', 'value' => 1]);
        $doc->name = 'b';
        $this->assertSame('a', $doc->getOriginal('name'));
        $this->assertSame(1, $doc->getOriginal('value'));
    }

    public function testGetOriginalWithoutFieldReturnsAllValues(): void
    {
        $id  = new ObjectId();
        $doc = $this->loaded(['_id' => $id, 'name' => 'a', 'value' => 1]);
        $doc->name = 'b';
        $original = $doc->getOriginal();
        $this->assertEquals($id, $original['_id']);
        $this->assertSame('a', $original['name']);
        $this->assertSame(1, $original['value']);
    }

    public function testGetOriginalRestoresDateTimeAndEnum(): void
    {
        $doc = (new \ReflectionClass(DocWithTypes::class))->newInstanceWithoutConstructor();
        $doc->bsonUnserialize(['_id' => new ObjectId(), 'title' => 't', 'status' => 'b', 'due' => new UTCDateTime(1700000000000)]);
        $doc->status = FixtureStatus::A;
        $doc->due = new DateTime('2030-01-01');

        $this->assertSame(FixtureStatus::B, $doc->getOriginal('status'));
        $this->assertInstanceOf(DateTime::class, $doc->getOriginal('due'));
        $this->assertSame(1700000000, $doc->getOriginal('due')->getTimestamp());
    }

    public function testGetOriginalOfNestedDocumentIsAnIndependentCopy(): void
    {
        $doc = (new \ReflectionClass(DocWithChild::class))->newInstanceWithoutConstructor();
        $doc->bsonUnserialize(['_id' => new ObjectId(), 'child' => new DocChild(['label' => 'avant'])]);
        $doc->child->label = 'après'; // mutation en profondeur

        $this->assertSame(['child'], $doc->getDirty());
        $original = $doc->getOriginal('child');
        $this->assertInstanceOf(DocChild::class, $original);
        $this->assertSame('avant', $original->label);

        $original->label = 'bidouille';
        $this->assertSame('avant', $doc->getOriginal('child')->label);
    }

    public function testGetOriginalIsNullWithoutKnownState(): void
    {
        $doc = new DocFixture(['name' => 'a']);
        $this->assertNull($doc->getOriginal('name'));
        $this->assertSame([], $doc->getOriginal());
    }

    public function testGetOriginalIsNullForUnknownField(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a']);
        $this->assertNull($doc->getOriginal('nope'));
    }

    public function testDirtyTrackingDoesNotLeakIntoSerialization(): void
    {
        $doc = $this->loaded(['_id' => new ObjectId(), 'name' => 'a']);
        $this->assertSame(['_id', 'name', 'value', 'created_at'], array_keys($doc->bsonSerialize()));
        $this->assertSame(['_id', 'name', 'value', 'created_at'], array_keys($doc->jsonSerialize()));
    }
}
