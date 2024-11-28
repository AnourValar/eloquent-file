<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;

interface DirectAccessInterface
{
    /**
     * Getting a direct link to the file
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param string|null $generate
     * @return string|null
     */
    public function directUrl(FilePhysical $filePhysical, ?string $generate = null): ?string;
}
