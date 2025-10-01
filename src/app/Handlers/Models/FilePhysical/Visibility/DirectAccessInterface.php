<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

interface DirectAccessInterface
{
    /**
     * Getting a direct link to the file
     *
     * @param string $disk
     * @param string $path
     * @return string
     */
    public function directUrl(string $disk, string $path): string;
}
