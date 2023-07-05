<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Contracts\Auth\Authenticatable;

interface EntityInterface
{
    /**
     * Can the user store the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return bool
     */
    public function canUpload(FileVirtual $fileVirtual, ?Authenticatable $user): bool;

    /**
     * Can the user access to the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return bool
     */
    public function canDownload(FileVirtual $fileVirtual, ?Authenticatable $user): bool;

    /**
     * Validation: entity, entity_id, name
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void;

    /**
     * Validation (delete)
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validateDelete(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void;
}
