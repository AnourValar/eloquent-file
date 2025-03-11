<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name;

use AnourValar\EloquentFile\FileVirtual;

class SimpleTitleName implements NameInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        $validator->addRules([
            'title' => ['required'],
            'details' => ['nullable', 'prohibited'],
        ]);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::canonizeDetails()
     */
    public function canonizeDetails($details): mixed
    {
        return $details;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::generateFake()
     */
    public function generateFake(string $entity, string $name, \Illuminate\Database\Eloquent\Model $entitable): array
    {
        return [
            'title' => \App::make(\Faker\Generator::class)->sentence(),
        ];
    }
}
