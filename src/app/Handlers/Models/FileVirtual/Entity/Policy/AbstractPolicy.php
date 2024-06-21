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
        $collection = null;

        if (! empty($fileVirtual->name_details['policy']['limit_qty'])) {
            \Atom::lockFileVirtual($fileVirtual->entity, $fileVirtual->entity_id, $fileVirtual->name);
            $collection = $this->getOriginalCollection($fileVirtual);

            if ($collection->count() + 1 > $fileVirtual->name_details['policy']['limit_qty']) {
                $validator->errors()->add(
                    'entity_id',
                    trans(
                        'eloquent-file::file_virtual.entity_handler.over_limit_qty',
                        ['name' => $fileVirtual->name_title, 'limit' => $fileVirtual->name_details['policy']['limit_qty']]
                    )
                );
            }
        }

        if (! empty($fileVirtual->name_details['policy']['limit_size'])) {
            if (! isset($collection)) {
                \Atom::lockFileVirtual($fileVirtual->entity, $fileVirtual->entity_id, $fileVirtual->name);
                $collection = $this->getOriginalCollection($fileVirtual);
            }

            $filePhysical = \App\FilePhysical::find($fileVirtual->file_physical_id);
            if (($collection->sum('size') + $filePhysical->size) / 1024 > $fileVirtual->name_details['policy']['limit_size']) {
                $validator->errors()->add(
                    'entity_id',
                    trans(
                        'eloquent-file::file_virtual.entity_handler.over_limit_size',
                        ['name' => $fileVirtual->name_title, 'limit' => $fileVirtual->name_details['policy']['limit_size']]
                    )
                );
            }
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getOriginalCollection(FileVirtual $fileVirtual): Collection
    {
        $class = config('eloquent_file.models.file_virtual');

        return $class::query()
            ->with('filePhysical')
            ->where('entity', '=', $fileVirtual->entity)
            ->where('entity_id', '=', $fileVirtual->entity_id)
            ->where('name', '=', $fileVirtual->name)
            ->where('id', '!=', $fileVirtual->id)
            ->get();
    }
}
