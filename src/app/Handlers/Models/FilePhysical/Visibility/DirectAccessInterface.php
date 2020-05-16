<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;

interface DirectAccessInterface
{
    /**
     * Getting a direct link to a file
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param string $path
     * @return string
     */
    public function getUrl(FilePhysical $filePhysical, string $path = null) : string;
}
