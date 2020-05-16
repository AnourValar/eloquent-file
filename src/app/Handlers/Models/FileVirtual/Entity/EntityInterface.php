<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Contracts\Auth\Authenticatable;

interface EntityInterface
{
    /**
     * Can a user get a direct link to a file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Contracts\Auth\Authenticatable $user
     * @return boolean
     */
    public function canDownload(FileVirtual $fileVirtual, ?Authenticatable $user) : bool;

    /**
     * Validation: entity, entity_id, name
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator) : void;
}
