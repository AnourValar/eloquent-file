<?php

namespace AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy;

use AnourValar\EloquentFile\FileVirtual;

class UniquePolicy extends AbstractPolicy implements PolicyInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface::onCreated()
     */
    public function onCreated(FileVirtual $fileVirtual) : void
    {
        foreach ($this->getOriginalCollection($fileVirtual) as $item) {
            $item->validateDelete()->delete();
        }
    }
}
