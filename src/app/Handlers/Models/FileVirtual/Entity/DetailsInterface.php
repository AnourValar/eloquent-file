<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Contracts\Auth\Authenticatable;

interface DetailsInterface
{
    /**
     * Validation: details
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validateDetails(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void;
}
