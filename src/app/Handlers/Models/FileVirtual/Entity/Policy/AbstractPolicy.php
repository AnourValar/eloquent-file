<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Database\Eloquent\Collection;

abstract class AbstractPolicy
{
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
