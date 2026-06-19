<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Services\FileService;
use AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class FileServiceTest extends AbstractSuite
{
    public function test_prepare_from_buffer(): void
    {
        // The service owns the temp resource; keep it alive while reading.
        $service = \App::make(FileService::class);
        $file = $service->prepareFromBuffer('hello world', 'note.txt', 'text/plain');

        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertSame('note.txt', $file->getClientOriginalName());
        $this->assertSame('hello world', file_get_contents($file->getPathname()));
    }

    public function test_prepare_from_path(): void
    {
        $file = \App::make(FileService::class)->prepareFromPath($this->fixturePath('document.txt'));

        $this->assertInstanceOf(UploadedFile::class, $file);
        $this->assertSame('document.txt', $file->getClientOriginalName());
    }

    public function test_upload_simple_creates_physical_and_virtual(): void
    {
        $user = $this->createUser();

        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'scan',
        ]);

        $this->assertTrue($fileVirtual->exists);
        $this->assertDatabaseCount('file_physicals', 1);
        $this->assertDatabaseCount('file_virtuals', 1);

        $filePhysical = $fileVirtual->filePhysical;
        $this->assertSame('private_encrypt', $filePhysical->visibility);
        $this->assertSame('simple', $filePhysical->type);
        $this->assertSame(hash_file('sha256', $this->fixturePath('document.txt')), $filePhysical->sha256);
        $this->assertSame(filesize($this->fixturePath('document.txt')), $filePhysical->size);
        $this->assertTrue($filePhysical->linked);
        Storage::disk($filePhysical->disk)->assertExists($filePhysical->path);
    }

    public function test_upload_image_dispatches_generate_job(): void
    {
        Bus::fake();
        $user = $this->createUser();

        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'gallery',
        ]);

        $this->assertSame('image', $fileVirtual->filePhysical->type);
        $this->assertSame('public', $fileVirtual->filePhysical->visibility);
        Bus::assertDispatched(\AnourValar\EloquentFile\Jobs\GenerateJob::class);
    }

    public function test_upload_deduplicates_identical_content(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();

        $first = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $userA->id, 'name' => 'scan',
        ]);
        $second = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $userB->id, 'name' => 'scan',
        ]);

        // The physical file is shared, only one row is stored
        $this->assertSame($first->file_physical_id, $second->file_physical_id);
        $this->assertDatabaseCount('file_physicals', 1);
        $this->assertDatabaseCount('file_virtuals', 2);
    }

    public function test_upload_rejects_file_with_existing_physical_id(): void
    {
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => 1, 'name' => 'scan', 'file_physical_id' => 5,
        ]);

        $this->expectException(\RuntimeException::class);
        \App::make(FileService::class)->upload($this->fixtureFile('document.txt'), $fileVirtual);
    }

    public function test_upload_rejects_persisted_virtual(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $this->expectException(\RuntimeException::class);
        \App::make(FileService::class)->upload($this->fixtureFile('document.txt'), $fileVirtual);
    }

    public function test_upload_validation_fails_for_wrong_extension(): void
    {
        $user = $this->createUser();

        // gallery accepts images only -> a txt file must be rejected
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $this->expectException(ValidationException::class);
        \DB::transaction(function () use (&$fileVirtual) {
            \App::make(FileService::class)->upload($this->fixtureFile('document.txt'), $fileVirtual);
        });
    }

    public function test_upload_validation_fails_for_too_small_image(): void
    {
        $user = $this->createUser();

        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $this->expectException(ValidationException::class);
        \DB::transaction(function () use (&$fileVirtual) {
            \App::make(FileService::class)->upload($this->fixtureFile('small.png'), $fileVirtual);
        });
    }

    public function test_collect_returns_matching_virtuals(): void
    {
        $user = $this->createUser();
        $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $collection = \App::make(FileService::class)->collect([
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'scan',
        ]);

        $this->assertCount(1, $collection);
        $this->assertTrue($collection->first()->relationLoaded('filePhysical'));
    }

    public function test_collect_validates_input(): void
    {
        $this->expectException(ValidationException::class);
        \App::make(FileService::class)->collect(['entity' => 'user']); // entity_id, name missing
    }

    public function test_replicate_creates_a_copy(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $copy = \App::make(FileService::class)->replicate(
            $fileVirtual,
            ['entity' => 'user', 'entity_id' => $other->id]
        );

        $this->assertTrue($copy->exists);
        $this->assertNotSame($fileVirtual->id, $copy->id);
        $this->assertSame($fileVirtual->file_physical_id, $copy->file_physical_id);
        $this->assertSame($other->id, $copy->entity_id);
    }

    public function test_upload_rolled_back_leaves_no_rows(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        try {
            \DB::transaction(function () use (&$fileVirtual) {
                \App::make(FileService::class)->upload($this->fixtureFile('document.txt'), $fileVirtual);
                throw new \DomainException('forced rollback');
            });
        } catch (\DomainException $e) {
            // expected
        }

        $this->assertDatabaseCount('file_physicals', 0);
        $this->assertDatabaseCount('file_virtuals', 0);
    }

    public function test_lock_requires_complete_attributes(): void
    {
        $this->expectException(\RuntimeException::class);
        \App::make(FileService::class)->lock(new FilePhysical());
    }

    public function test_lock_passes_for_complete_attributes(): void
    {
        $filePhysical = (new FilePhysical())->forceFill([
            'visibility' => 'public', 'type' => 'simple', 'sha256' => str_repeat('a', 64),
        ]);

        \DB::transaction(function () use ($filePhysical) {
            \App::make(FileService::class)->lock($filePhysical);
        });

        $this->assertNotEmpty(\AnourValar\EloquentFile\Tests\Support\NoLockStrategy::$keys);
    }
}
