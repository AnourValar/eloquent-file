<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Contracts\Auth\Authenticatable;

class UserEntity implements EntityInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::canAccess()
     */
    public function canAccess(FileVirtual $fileVirtual, ?Authenticatable $user): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        $class = config('auth.providers.users.model');

        if (! $class::find($fileVirtual->entity_id)) {
            $validator->errors()->add(
                'entity_id',
                trans('eloquent-file::file_virtual.entity_handlers.user.entity_id_not_exists')
            );
        }
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::validateDelete()
     */
    public function validateDelete(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {

    }
}
