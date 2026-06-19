<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\UniquePolicy;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\UserEntity;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Support\Facades\Bus;

class FileVirtualModelTest extends AbstractSuite
{
    public function test_entity_name_details_virtual_attributes(): void
    {
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'name' => 'avatar']);

        $this->assertSame(config('eloquent_file.file_virtual.entity.user'), $fileVirtual->entity_details);
        $this->assertSame(config('eloquent_file.file_virtual.entity.user.name.avatar'), $fileVirtual->name_details);
        $this->assertIsString($fileVirtual->name_title);
    }

    public function test_get_handlers(): void
    {
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'name' => 'avatar']);

        $this->assertInstanceOf(UserEntity::class, $fileVirtual->getEntityHandler());
        $this->assertInstanceOf(SimpleName::class, $fileVirtual->getNameHandler());
        $this->assertInstanceOf(UniquePolicy::class, $fileVirtual->getEntityPolicyHandler());
    }

    public function test_mime_and_size_delegate_to_physical(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $fileVirtual->load('filePhysical');

        $this->assertSame($fileVirtual->filePhysical->mime_type, $fileVirtual->mime_type);
        $this->assertSame($fileVirtual->filePhysical->size, $fileVirtual->size);
    }

    public function test_url_for_public_direct_access(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note', // public/simple
        ]);
        $fileVirtual->load('filePhysical');

        $this->assertStringContainsString($fileVirtual->filePhysical->path, $fileVirtual->url);
    }

    public function test_url_for_private_proxy(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'Doc',
        ]);
        $fileVirtual->load('filePhysical');

        $this->assertStringContainsString('/file/'.$fileVirtual->id.'/download/', $fileVirtual->url);
    }

    public function test_url_requires_eager_loaded_relation(): void
    {
        $fileVirtual = new FileVirtual();
        $this->expectException(\LogicException::class);
        $fileVirtual->url;
    }

    public function test_url_generate_returns_generated_files(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        // Run generation
        (new GenerateJob($fileVirtual->filePhysical))->handle(\App::make(\AnourValar\EloquentFile\Services\FileService::class));

        $fresh = FileVirtual::with('filePhysical')->find($fileVirtual->id);
        $this->assertIsArray($fresh->url_generate);
        $this->assertArrayHasKey('preview', $fresh->url_generate);
        $this->assertStringContainsString('.webp', $fresh->url_generate['preview']);
    }

    public function test_url_generate_null_without_generated(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $fileVirtual->load('filePhysical');

        $this->assertNull($fileVirtual->url_generate);
    }

    public function test_scope_light(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $light = FileVirtual::light()->findOrFail($fileVirtual->id);
        $array = $light->toArray();

        $this->assertArrayHasKey('url', $array);
        $this->assertArrayHasKey('mime_type', $array);
        $this->assertArrayHasKey('size', $array);
        $this->assertArrayNotHasKey('entity', $array);
        $this->assertArrayNotHasKey('file_physical_id', $array);
    }

    public function test_entitable_relation(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $this->assertTrue($fileVirtual->entitable->is($user));
    }

    public function test_validation_rejects_missing_physical(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
            'file_physical_id' => 999999, 'filename' => 'x.txt',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_validation_rejects_visibility_mismatch(): void
    {
        $user = $this->createUser();
        // private_encrypt physical
        $scan = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        // "note" expects public visibility
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
            'file_physical_id' => $scan->file_physical_id, 'filename' => 'x.txt',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_validation_rejects_type_mismatch(): void
    {
        Bus::fake();
        $user = $this->createUser();
        // public/image physical
        $gallery = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        // "note" expects simple type (same public visibility, different type)
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
            'file_physical_id' => $gallery->file_physical_id, 'filename' => 'x.png',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_validation_rejects_unknown_name(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'no_such_name',
            'file_physical_id' => 1, 'filename' => 'x.txt',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_unchangeable_columns(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $fileVirtual->filename = 'changed.txt'; // unchangeable
        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }
}
