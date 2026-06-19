<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateEncryptVisibility;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PublicVisibility;
use AnourValar\EloquentValidation\Exceptions\ValidationException;

class FilePhysicalModelTest extends AbstractSuite
{
    public function test_default_attributes_and_casts(): void
    {
        $filePhysical = new FilePhysical();
        $this->assertFalse($filePhysical->linked);

        $filePhysical->forceFill(['size' => '123', 'linked' => 1]);
        $this->assertSame(123, $filePhysical->size);
        $this->assertTrue($filePhysical->linked);
    }

    public function test_visibility_and_type_details_virtual_attributes(): void
    {
        $filePhysical = (new FilePhysical())->forceFill(['visibility' => 'public', 'type' => 'image']);

        $this->assertSame(
            config('eloquent_file.file_physical.visibility.public'),
            $filePhysical->visibility_details
        );
        $this->assertSame(
            config('eloquent_file.file_physical.type.image'),
            $filePhysical->type_details
        );
    }

    public function test_get_handlers(): void
    {
        $public = (new FilePhysical())->forceFill(['visibility' => 'public', 'type' => 'simple']);
        $this->assertInstanceOf(PublicVisibility::class, $public->getVisibilityHandler());
        $this->assertInstanceOf(SimpleType::class, $public->getTypeHandler());

        $enc = (new FilePhysical())->forceFill(['visibility' => 'private_encrypt']);
        $this->assertInstanceOf(PrivateEncryptVisibility::class, $enc->getVisibilityHandler());
    }

    public function test_content_attribute_reads_through_adapter(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan', // private_encrypt
        ]);

        $filePhysical = $fileVirtual->filePhysical;
        $this->assertSame(file_get_contents($this->fixturePath('document.txt')), $filePhysical->content);
    }

    public function test_content_attribute_reads_plain_storage(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery', // public, no adapter
        ]);

        $filePhysical = $fileVirtual->filePhysical;
        $this->assertSame(file_get_contents($this->fixturePath('image.png')), $filePhysical->content);
    }

    public function test_file_virtuals_relation(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);

        $filePhysical = $fileVirtual->filePhysical;
        $this->assertTrue($filePhysical->fileVirtuals()->where('id', $fileVirtual->id)->exists());
    }

    public function test_validation_requires_visibility_and_type(): void
    {
        $this->expectException(ValidationException::class);
        (new FilePhysical())->forceFill([
            'disk' => 's3_public',
            'sha256' => str_repeat('a', 64),
            'size' => 1,
        ])->validate();
    }

    public function test_validation_rejects_unknown_visibility(): void
    {
        $this->expectException(ValidationException::class);
        (new FilePhysical())->forceFill([
            'visibility' => 'no_such_visibility',
            'type' => 'simple',
            'disk' => 's3_public',
            'sha256' => str_repeat('a', 64),
            'size' => 1,
        ])->validate();
    }

    public function test_unchangeable_columns_cannot_be_modified(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        $filePhysical->type = 'image'; // "type" is unchangeable
        $this->expectException(ValidationException::class);
        $filePhysical->validate();
    }

    public function test_hidden_attributes_are_not_serialized(): void
    {
        $user = $this->createUser();
        $filePhysical = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ])->filePhysical;

        $array = $filePhysical->toArray();
        foreach (['id', 'visibility', 'type', 'disk', 'path', 'sha256', 'linked'] as $hidden) {
            $this->assertArrayNotHasKey($hidden, $array);
        }
    }
}
