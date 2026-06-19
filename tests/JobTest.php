<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Jobs\AfterGenerateJob;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentFile\Jobs\OnZeroJob;
use AnourValar\EloquentFile\Services\FileService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class JobTest extends AbstractSuite
{
    /**
     * @return \AnourValar\EloquentFile\Services\FileService
     */
    private function fileService(): FileService
    {
        return \App::make(FileService::class);
    }

    public function test_generate_job_builds_previews_and_keeps_original(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);
        $filePhysical = $fileVirtual->filePhysical;
        $originalPath = $filePhysical->path;

        (new GenerateJob($filePhysical))->handle($this->fileService());

        $fresh = FilePhysical::find($filePhysical->id);
        $this->assertArrayHasKey('preview', (array) $fresh->path_generate);
        $this->assertSame($originalPath, $fresh->path); // keep_original = true
        Storage::disk($fresh->path_generate['preview']['disk'])->assertExists($fresh->path_generate['preview']['path']);

        Bus::assertDispatched(AfterGenerateJob::class);
    }

    public function test_generate_job_drops_original_when_not_kept(): void
    {
        Bus::fake();
        config()->set('eloquent_file.file_physical.type.image.keep_original', false);

        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        (new GenerateJob($filePhysical))->handle($this->fileService());

        $this->assertNull(FilePhysical::find($filePhysical->id)->path);
    }

    public function test_after_generate_job_removes_stale_files(): void
    {
        $disk = 's3_public';
        Storage::disk($disk)->put('old/preview.webp', 'old');
        Storage::disk($disk)->put('orig.png', 'orig');

        $filePhysical = (new FilePhysical())->forceFill([
            'visibility' => 'public', 'type' => 'image', 'disk' => $disk,
            'sha256' => str_repeat('a', 64), 'size' => 10,
        ]);
        $filePhysical->path = null; // original should be deleted
        $filePhysical->path_generate = ['preview' => ['disk' => $disk, 'path' => 'new/preview.webp', 'visibility' => 'public', 'mime_type' => 'image/webp']];

        (new AfterGenerateJob(
            $filePhysical,
            ['preview' => ['disk' => $disk, 'path' => 'old/preview.webp']],
            'orig.png'
        ))->handle();

        Storage::disk($disk)->assertMissing('old/preview.webp'); // stale generated removed
        Storage::disk($disk)->assertMissing('orig.png'); // original removed (path === null)
    }

    public function test_after_generate_job_keeps_unchanged_files(): void
    {
        $disk = 's3_public';
        Storage::disk($disk)->put('same/preview.webp', 'same');

        $filePhysical = (new FilePhysical())->forceFill([
            'visibility' => 'public', 'type' => 'image', 'disk' => $disk,
            'sha256' => str_repeat('a', 64), 'size' => 10, 'path' => 'orig.png',
        ]);
        $filePhysical->path_generate = ['preview' => ['disk' => $disk, 'path' => 'same/preview.webp', 'visibility' => 'public', 'mime_type' => 'image/webp']];

        (new AfterGenerateJob(
            $filePhysical,
            ['preview' => ['disk' => $disk, 'path' => 'same/preview.webp', 'visibility' => 'public', 'mime_type' => 'image/webp']],
            'orig.png'
        ))->handle();

        Storage::disk($disk)->assertExists('same/preview.webp'); // unchanged -> kept
    }

    public function test_on_zero_job_deletes_orphaned_physical(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;
        $disk = $filePhysical->disk;
        $path = $filePhysical->path;

        // Orphan it and backdate (simulate >2h old & unlinked)
        $fileVirtual->forceDelete();
        FilePhysical::where('id', $filePhysical->id)->update(['linked' => false, 'updated_at' => now()->subHours(3)]);

        (new OnZeroJob(FilePhysical::find($filePhysical->id)))->handle($this->fileService());

        $this->assertDatabaseMissing('file_physicals', ['id' => $filePhysical->id]);
        Storage::disk($disk)->assertMissing($path);
    }

    public function test_on_zero_job_skips_recently_updated(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        // Unlinked but updated just now -> must be skipped
        $fileVirtual->forceDelete();
        FilePhysical::where('id', $filePhysical->id)->update(['linked' => false, 'updated_at' => now()]);

        (new OnZeroJob(FilePhysical::find($filePhysical->id)))->handle($this->fileService());

        $this->assertDatabaseHas('file_physicals', ['id' => $filePhysical->id]);
    }

    public function test_on_zero_job_relinks_when_virtual_exists(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        // Inconsistent state: unlinked & old, but a virtual still references it
        FilePhysical::where('id', $filePhysical->id)->update(['linked' => false, 'updated_at' => now()->subHours(3)]);

        (new OnZeroJob(FilePhysical::find($filePhysical->id)))->handle($this->fileService());

        $this->assertTrue(FilePhysical::find($filePhysical->id)->linked);
    }
}
