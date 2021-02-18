<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FileVirtual;

interface ProxyInterface
{
    /**
     * Generate URL to the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return string
     */
    public function generateUrl(FileVirtual $fileVirtual): string;

    /**
     * Downloading (proxying) a file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response;
}
