<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\UserEntity;
use AnourValar\EloquentFile\Tests\Support\NoLockStrategy;

class EntityHandlerTest extends AbstractSuite
{
    public function test_can_upload_for_owner(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $user->id]);

        $this->assertTrue((new UserEntity())->canUpload($fileVirtual, $user));
        $this->assertTrue((new UserEntity())->canDownload($fileVirtual, $user));
        $this->assertTrue((new UserEntity())->canDelete($fileVirtual, $user));
    }

    public function test_cannot_upload_for_other_user(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $owner->id]);

        $this->assertFalse((bool) (new UserEntity())->canUpload($fileVirtual, $stranger));
    }

    public function test_lock_on_change(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $user->id]);

        NoLockStrategy::$keys = [];
        (new UserEntity())->lockOnChange($fileVirtual);

        $this->assertNotEmpty(NoLockStrategy::$keys);
    }

    public function test_validate_passes_for_existing_user(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $user->id]);

        $validator = \Validator::make([], []);
        (new UserEntity())->validate($fileVirtual, $validator);

        $this->assertFalse($validator->errors()->has('entity_id'));
    }

    public function test_validate_fails_for_missing_user(): void
    {
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => 999999]);

        $validator = \Validator::make([], []);
        (new UserEntity())->validate($fileVirtual, $validator);

        $this->assertTrue($validator->errors()->has('entity_id'));
    }

    public function test_validate_delete_is_noop(): void
    {
        $user = $this->createUser();
        $fileVirtual = (new FileVirtual())->forceFill(['entity' => 'user', 'entity_id' => $user->id]);

        $validator = \Validator::make([], []);
        (new UserEntity())->validateDelete($fileVirtual, $validator);

        $this->assertTrue($validator->passes());
    }
}
