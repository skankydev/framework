<?php

namespace SkankyTest\TestCase\Model;

use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\TestCase;
use SkankyDev\Model\Document\EmbeddedSnapshot;
use SkankyDev\Model\Document\MasterDocument;

// ── Fixtures ──────────────────────────────────────────────────────────────────

class SnapUser extends MasterDocument {
	public string $name = '';
	public string $avatar = '';
	public string $password = '';
}

class SnapUserSnapshot extends EmbeddedSnapshot {
	public string $name = '';
	public string $avatar = '';
	public string $missing = 'défaut'; // absent de SnapUser
}

// ── Tests ─────────────────────────────────────────────────────────────────────

class EmbeddedSnapshotTest extends TestCase
{
    private function savedUser(): SnapUser {
        $user = new SnapUser(['name' => 'Simon', 'avatar' => 'simon.png', 'password' => 'secret']);
        $user->_id = new ObjectId();
        return $user;
    }

    public function testCopiesDeclaredFieldsAndId(): void {
        $user = $this->savedUser();
        $snap = new SnapUserSnapshot($user);

        $this->assertSame('Simon', $snap->name);
        $this->assertSame('simon.png', $snap->avatar);
        $this->assertEquals($user->_id, $snap->_id);
    }

    public function testDoesNotCopyUndeclaredFields(): void {
        $snap = new SnapUserSnapshot($this->savedUser());
        $this->assertArrayNotHasKey('password', get_object_vars($snap));
    }

    public function testFieldMissingInSourceKeepsDefault(): void {
        $snap = new SnapUserSnapshot($this->savedUser());
        $this->assertSame('défaut', $snap->missing);
    }

    public function testIsNotDeletedByDefault(): void {
        $snap = new SnapUserSnapshot($this->savedUser());
        $this->assertFalse($snap->_deleted);
    }

    public function testThrowsWhenSourceHasNoId(): void {
        $this->expectException(\LogicException::class);
        new SnapUserSnapshot(new SnapUser(['name' => 'jamais sauvegardé']));
    }

    public function testCanBeBuiltFromArray(): void {
        $hex  = '5099803df3f4948bd2f98391';
        $snap = new SnapUserSnapshot(['_id' => $hex, 'name' => 'Simon']);
        $this->assertSame($hex, (string) $snap->_id);
        $this->assertSame('Simon', $snap->name);
    }

    public function testCopyFromRefreshesExistingSnapshot(): void {
        $user = $this->savedUser();
        $snap = new SnapUserSnapshot($user);
        $user->avatar = 'nouveau.png';

        $snap->copyFrom($user);
        $this->assertSame('nouveau.png', $snap->avatar);
    }

    public function testSyncedFieldsExcludesFrameworkFields(): void {
        $this->assertSame(['name', 'avatar', 'missing'], SnapUserSnapshot::syncedFields());
    }

    public function testBsonRoundTripRestoresSnapshot(): void {
        $snap = new SnapUserSnapshot($this->savedUser());
        $back = Document::fromPHP(['author' => $snap])->toPHP(['root' => 'array'])['author'];

        $this->assertInstanceOf(SnapUserSnapshot::class, $back);
        $this->assertSame('Simon', $back->name);
        $this->assertEquals($snap->_id, $back->_id);
        $this->assertFalse($back->_deleted);
    }

    public function testJsonSerializeConvertsIdToString(): void {
        $snap = new SnapUserSnapshot($this->savedUser());
        $json = $snap->jsonSerialize();
        $this->assertSame((string) $snap->_id, $json['_id']);
    }
}
