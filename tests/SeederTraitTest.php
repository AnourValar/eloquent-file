<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Traits\SeederTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SeederTraitTest extends AbstractSuite
{
    /**
     * @return object
     */
    private function seeder(): object
    {
        return new class ()
        {
            use SeederTrait;

            public function fake(): self
            {
                return $this->fakeStorages();
            }

            public function buffer(FileVirtual &$fileVirtual, Model $entitable, string $binary): void
            {
                $this->createFromBuffer($fileVirtual, $entitable, $binary);
            }

            public function list(FileVirtual &$fileVirtual, Model $entitable, string $path, array $files = []): void
            {
                $this->createFromList($fileVirtual, $entitable, $path, $files);
            }
        };
    }

    public function test_fake_storages_returns_self(): void
    {
        $seeder = $this->seeder();
        $this->assertSame($seeder, $seeder->fake());
    }

    public function test_create_from_buffer(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['name' => 'blob']);

        $this->seeder()->buffer($fileVirtual, $user, 'binary-content');

        $this->assertTrue($fileVirtual->exists);
        $this->assertSame('user', $fileVirtual->entity);
        $this->assertSame($user->id, $fileVirtual->entity_id);
        Storage::disk($fileVirtual->filePhysical->disk)->assertExists($fileVirtual->filePhysical->path);
        $this->assertSame('binary-content', file_get_contents($this->fixtureContentPath($fileVirtual)));
    }

    public function test_create_from_buffer_runs_name_generate_fake(): void
    {
        $user = $this->createUser();
        // "meta" uses DetailsName whose generateFake() returns ['details' => ['fake' => true]]
        $fileVirtual = (new FileVirtual())->forceFill(['name' => 'meta']);

        $this->seeder()->buffer($fileVirtual, $user, 'x');

        $this->assertTrue($fileVirtual->fresh()->details['fake']);
    }

    public function test_create_from_list_with_explicit_files(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['name' => 'note']);

        $this->seeder()->list($fileVirtual, $user, $this->fixturePath(''), ['document.txt']);

        $this->assertTrue($fileVirtual->exists);
        $this->assertSame('document.txt', $fileVirtual->filename);
        Storage::disk($fileVirtual->filePhysical->disk)->assertExists($fileVirtual->filePhysical->path);
    }

    public function test_create_from_list_scans_directory(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['name' => 'note']);

        // No explicit list -> the trait scans the directory and picks a file
        $this->seeder()->list($fileVirtual, $user, $this->fixturePath(''));

        $this->assertTrue($fileVirtual->exists);
        $this->assertNotNull($fileVirtual->file_physical_id);
    }

    /**
     * Helper: resolve the on-disk content path for the stored physical file.
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return string
     */
    private function fixtureContentPath(FileVirtual $fileVirtual): string
    {
        return Storage::disk($fileVirtual->filePhysical->disk)->path($fileVirtual->filePhysical->path);
    }
}
