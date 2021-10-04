<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;

interface PolicyInterface
{
    /**
     * Validation
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validate(FileVirtual $fileVirtual, \Illuminate\Validation\Validator $validator): void;

    /**
     * Apply retention policy
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return void
     */
    public function onCreated(FileVirtual $fileVirtual): void;
}
