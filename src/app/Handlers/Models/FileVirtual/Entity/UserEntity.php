<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Contracts\Auth\Authenticatable;

class UserEntity implements EntityInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::canUpload()
     */
    public function canUpload(FileVirtual $fileVirtual, ?Authenticatable $user): bool
    {
        return $user?->can('update', $fileVirtual->entitable);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::canDownload()
     */
    public function canDownload(FileVirtual $fileVirtual, ?Authenticatable $user): bool
    {
        return $this->canUpload($fileVirtual, $user);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::canDelete()
     */
    public function canDelete(FileVirtual $fileVirtual, ?Authenticatable $user): bool
    {
        return $this->canUpload($fileVirtual, $user);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        $class = config('auth.providers.users.model');

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses($class))) {
            $user = $class::withTrashed()->find($fileVirtual->entity_id);
        } else {
            $user = $class::find($fileVirtual->entity_id);
        }

        if (! $user) {
            $validator->errors()->add(
                'entity_id',
                trans('eloquent-file::file_virtual.entity_handler.user.entity_id_not_exists')
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
