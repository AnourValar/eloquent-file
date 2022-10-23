<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name;

use AnourValar\EloquentFile\FileVirtual;

interface NameInterface
{
    /**
     * Specific validation: title, details ...
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void;

    /**
     * Canonization the details (casting, etc)
     *
     * @param mixed $details
     * @return mixed
     */
    public function canonizeDetails($details): mixed;

    /**
     * Generates fake attributes: title, details ...
     *
     * @param string $entity
     * @param string $name
     * @return array
     */
    public function generateFake(string $entity, string $name): array;
}
