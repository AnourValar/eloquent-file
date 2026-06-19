<?php

namespace AnourValar\EloquentFile\Tests\Support;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface;

/**
 * Name handler that accepts "details" (used to exercise the canonizeDetails() path).
 */
class DetailsName implements NameInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::validate()
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void
    {
        $validator->addRules([
            'title' => ['nullable', 'prohibited'],
            'details' => ['required', 'array'],
        ]);
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::canonizeDetails()
     */
    public function canonizeDetails($details): mixed
    {
        if (is_array($details)) {
            $details['canonized'] = true;
        }

        return $details;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface::generateFake()
     */
    public function generateFake(string $entity, string $name, \Illuminate\Database\Eloquent\Model $entitable): array
    {
        return ['details' => ['fake' => true]];
    }
}
