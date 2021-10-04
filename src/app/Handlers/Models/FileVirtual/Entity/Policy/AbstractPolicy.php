<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Database\Eloquent\Collection;

abstract class AbstractPolicy implements PolicyInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        if (empty($fileVirtual->name_details['policy']['limit'])) {
            return;
        }

        if ($this->getOriginalCollection($fileVirtual)->count() + 1 > $fileVirtual->name_details['policy']['limit']) {
            $validator->errors()->add(
                'entity_id',
                trans(
                    'eloquent-file::file_virtual.entity_handlers.over_limit',
                    ['name' => $fileVirtual->name_title, 'limit' => $fileVirtual->name_details['policy']['limit']]
                )
            );
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getOriginalCollection(FileVirtual $fileVirtual): Collection
    {
        $class = config('eloquent_file.models.file_virtual');

        return $class
            ::where('entity', '=', $fileVirtual->entity)
            ->where('entity_id', '=', $fileVirtual->entity_id)
            ->where('name', '=', $fileVirtual->name)
            ->where('id', '!=', $fileVirtual->id)
            ->get();
    }
}
