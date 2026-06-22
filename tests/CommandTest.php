<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentFile\Jobs\OnZeroJob;
use Illuminate\Support\Facades\Bus;

class CommandTest extends AbstractSuite
{
    public function test_on_zero_no_jobs(): void
    {
        Bus::fake();

        $this->artisan('eloquent_file:on-zero')
            ->expectsOutputToContain('No jobs.')
            ->assertSuccessful();

        Bus::assertNotDispatched(OnZeroJob::class);
    }

    public function test_on_zero_dispatches_for_orphans(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        // Make it an old orphan
        $fileVirtual->forceDelete();
        FilePhysical::where('id', $filePhysical->id)->update(['linked' => false, 'updated_at' => now()->subHours(3)]);

        $this->artisan('eloquent_file:on-zero')
            ->expectsOutputToContain('job(s) created.')
            ->assertSuccessful();

        Bus::assertDispatched(OnZeroJob::class);
    }

    public function test_on_zero_with_days_option(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        $fileVirtual->forceDelete();
        // 3 days ago -> selected by --days=2
        FilePhysical::where('id', $filePhysical->id)->update(['linked' => false, 'updated_at' => now()->subDays(3)]);

        $this->artisan('eloquent_file:on-zero', ['--days' => 2])->assertSuccessful();

        Bus::assertDispatched(OnZeroJob::class);
    }

    public function test_regenerate_dispatches_for_generatable_types(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $this->artisan('eloquent_file:regenerate', ['--created_before' => '+1 minute'])
            ->assertSuccessful();

        Bus::assertDispatched(GenerateJob::class);
    }

    public function test_regenerate_skips_when_nothing_matches(): void
    {
        Bus::fake();
        $user = $this->createUser();
        // a non-generatable (simple) file only
        $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $this->artisan('eloquent_file:regenerate', ['--created_before' => '+1 minute'])
            ->assertSuccessful();

        Bus::assertNotDispatched(GenerateJob::class);
    }
}
