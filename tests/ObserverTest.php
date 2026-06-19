<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\Events\FileVirtualChanged;
use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

class ObserverTest extends AbstractSuite
{
    public function test_virtual_saving_canonizes_details(): void
    {
        $user = $this->createUser();

        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'meta',
            'details' => ['b' => 2, 'a' => 1],
        ]);

        // DetailsName::canonizeDetails() injects the "canonized" marker
        $this->assertTrue($fileVirtual->fresh()->details['canonized']);
    }

    public function test_virtual_created_fires_event(): void
    {
        Event::fake([FileVirtualChanged::class]);
        $user = $this->createUser();

        $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        Event::assertDispatched(FileVirtualChanged::class);
    }

    public function test_virtual_updated_fires_event_on_weight_change(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        Event::fake([FileVirtualChanged::class]);
        $fileVirtual->weight = 5;
        $fileVirtual->validate()->save();

        Event::assertDispatched(FileVirtualChanged::class);
    }

    public function test_virtual_updated_no_event_on_title_only(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'A',
        ]);

        Event::fake([FileVirtualChanged::class]);
        $fileVirtual->title = 'B';
        $fileVirtual->validate()->save();

        Event::assertNotDispatched(FileVirtualChanged::class);
    }

    public function test_virtual_deleted_unlinks_physical(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);
        $physicalId = $fileVirtual->file_physical_id;

        $this->assertTrue(FilePhysical::find($physicalId)->linked);

        $fileVirtual->delete();

        $this->assertFalse(FilePhysical::find($physicalId)->linked);
    }

    public function test_physical_deleting_guard_when_linked(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $this->expectException(\RuntimeException::class);
        $fileVirtual->filePhysical->delete();
    }

    public function test_physical_deleted_removes_files(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);
        $filePhysical = $fileVirtual->filePhysical;
        $disk = $filePhysical->disk;
        $path = $filePhysical->path;

        Storage::disk($disk)->assertExists($path);

        $fileVirtual->delete(); // unlink
        \DB::transaction(function () use ($filePhysical) {
            $filePhysical->fresh()->delete();
        });

        Storage::disk($disk)->assertMissing($path);
    }
}
