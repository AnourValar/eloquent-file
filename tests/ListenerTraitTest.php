<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentFile\Services\FileService;
use AnourValar\EloquentFile\Traits\ListenerTrait;
use Illuminate\Support\Facades\Bus;

class ListenerTraitTest extends AbstractSuite
{
    /**
     * @return object
     */
    private function listener(): object
    {
        return new class ()
        {
            use ListenerTrait;

            public function schema(FileVirtual $fileVirtual, $keys = 'preview'): ?array
            {
                return $this->avatarSchema($fileVirtual, $keys);
            }
        };
    }

    /**
     * @param \AnourValar\EloquentFile\Tests\Models\User $user
     * @param string $name
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    private function spec($user, string $name): FileVirtual
    {
        return (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $user->id, 'name' => $name]);
    }

    public function test_returns_null_when_no_file(): void
    {
        $user = $this->createUser();

        $this->assertNull($this->listener()->schema($this->spec($user, 'gallery')));
    }

    public function test_returns_non_generated_url(): void
    {
        $user = $this->createUser();
        $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $schema = $this->listener()->schema($this->spec($user, 'note'));

        $this->assertFalse($schema['generated']);
        $this->assertNotEmpty($schema['url']);
    }

    public function test_returns_generated_url(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);
        (new GenerateJob($fileVirtual->filePhysical))->handle(\App::make(FileService::class));

        $schema = $this->listener()->schema($this->spec($user, 'gallery'));

        $this->assertTrue($schema['generated']);
        $this->assertStringContainsString('.webp', $schema['url']);
    }

    public function test_ignores_archived_files(): void
    {
        $user = $this->createUser();
        // scan uses ArchivePolicy: the first upload becomes archived
        $first = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $second = $this->uploadFixture('document2.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $this->assertNotNull($first->fresh()->archived_at);

        $schema = $this->listener()->schema($this->spec($user, 'scan'));

        // only the active (non-archived) file is returned
        $this->assertFalse($schema['generated']);
        $this->assertNotEmpty($schema['url']);
    }
}
