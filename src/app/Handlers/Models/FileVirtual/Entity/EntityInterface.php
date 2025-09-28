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
     * Can the user delete the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return bool
     */
    public function canDelete(FileVirtual $fileVirtual, ?Authenticatable $user): bool;

    /**
     * Atomic lock (if required)
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return void
     */
    public function lockOnChange(FileVirtual $fileVirtual): void;

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
