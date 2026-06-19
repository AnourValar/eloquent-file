<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Services\FileService;
use Illuminate\Support\Facades\Artisan;

class ProviderFacadeTest extends AbstractSuite
{
    public function test_config_is_merged(): void
    {
        $this->assertSame(FilePhysical::class, config('eloquent_file.models.file_physical'));
        $this->assertSame(FileVirtual::class, config('eloquent_file.models.file_virtual'));
        $this->assertIsArray(config('eloquent_file.file_physical.visibility'));
        $this->assertIsArray(config('eloquent_file.file_physical.type'));
    }

    public function test_facade_resolves_file_service(): void
    {
        $this->assertInstanceOf(FileService::class, \EloquentFile::getFacadeRoot());
    }

    public function test_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('eloquent-file:on-zero', $commands);
        $this->assertContains('eloquent-file:regenerate', $commands);
    }

    public function test_observers_are_registered(): void
    {
        // FilePhysicalObserver::deleting throws when the model is still linked
        $filePhysical = (new FilePhysical())->forceFill([
            'visibility' => 'public', 'type' => 'simple', 'disk' => 's3_public',
            'sha256' => str_repeat('a', 64), 'size' => 1,
        ]);
        $filePhysical->forceFill(['linked' => true])->save();

        $this->expectException(\RuntimeException::class);
        $filePhysical->delete();
    }
}
