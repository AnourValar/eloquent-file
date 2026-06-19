<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Support\Facades\Bus;

class PolicyHandlerTest extends AbstractSuite
{
    public function test_unique_policy_keeps_only_latest(): void
    {
        Bus::fake(); // suppress GenerateJob for image uploads
        $user = $this->createUser();

        $first = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'avatar',
        ]);
        $second = $this->uploadFixture('photo.jpg', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'avatar',
        ]);

        // UniquePolicy removes the previous avatar
        $this->assertDatabaseMissing('file_virtuals', ['id' => $first->id]);
        $this->assertDatabaseHas('file_virtuals', ['id' => $second->id]);
        $this->assertSame(1, FileVirtual::where('entity_id', $user->id)->where('name', 'avatar')->count());
    }

    public function test_list_policy_keeps_all(): void
    {
        Bus::fake();
        $user = $this->createUser();

        $this->uploadFixture('image.png', ['entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery']);
        $this->uploadFixture('photo.jpg', ['entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery']);

        $this->assertSame(2, FileVirtual::where('entity_id', $user->id)->where('name', 'gallery')->count());
    }

    public function test_archive_policy_archives_previous(): void
    {
        $user = $this->createUser();

        $first = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $second = $this->uploadFixture('document2.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $this->assertNotNull($first->fresh()->archived_at);
        $this->assertNull($second->fresh()->archived_at);
    }

    public function test_list_policy_qty_limit(): void
    {
        $user = $this->createUser();

        // document: ListPolicy limit_qty = 2
        $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'A',
        ]);
        $this->uploadFixture('document2.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'B',
        ]);

        $this->expectException(ValidationException::class);
        $this->uploadFixture('document3.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'C',
        ]);
    }

    public function test_list_policy_size_limit(): void
    {
        $user = $this->createUser();

        // document: limit_size = 50 KiB; large.txt is 60 KiB
        $this->expectException(ValidationException::class);
        $this->uploadFixture('large.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'Big',
        ]);
    }
}
