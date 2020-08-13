<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;

interface PolicyInterface
{
    /**
     * Apply retention policy
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return void
     */
    public function onCreated(FileVirtual $fileVirtual): void;
}
