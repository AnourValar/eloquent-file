<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;

class ListPolicy implements PolicyInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface::onCreated()
     */
    public function onCreated(FileVirtual $fileVirtual): void
    {
        //
    }
}
