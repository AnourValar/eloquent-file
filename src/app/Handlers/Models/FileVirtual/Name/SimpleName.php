<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name;

use AnourValar\EloquentFile\FileVirtual;

class SimpleName implements NameInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        $validator->addRules([
            'details' => ['prohibited'],
        ]);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::generateFake()
     */
    public function generateFake(string $entity, string $name): array
    {
        return [];
    }
}
