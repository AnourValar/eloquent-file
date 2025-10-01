<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FileVirtual;

interface ProxyAccessInterface
{
    /**
     * Generates proxy URL to the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string|null $generate
     * @return string
     */
    public function proxyUrl(FileVirtual $fileVirtual, ?string $generate = null): string;
}
