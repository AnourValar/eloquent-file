<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;

class ArchivePolicy extends AbstractPolicy
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface::onCreated()
     */
    public function onCreated(FileVirtual $fileVirtual): void
    {
        foreach ($this->getOriginalCollection($fileVirtual) as $item) {
            $item->archived_at = now();
            $item->validate()->save();
        }
    }
}
