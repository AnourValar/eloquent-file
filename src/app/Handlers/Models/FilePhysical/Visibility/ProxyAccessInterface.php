<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FileVirtual;

interface ProxyAccessInterface
{
    /**
     * Generates proxy URL to the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return string
     */
    public function proxyUrl(FileVirtual $fileVirtual): string;

    /**
     * Download (via proxy) the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function proxyDownload(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response;

    /**
     * Retrieve (via proxy) the file
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function proxyInline(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response;
}
