<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

interface AdapterInterface
{
    /**
     * Store the uploaded file
     *
     * @param string $disk
     * @param string $path
     * @param string $content
     * @return void
     */
    public function putFile(string $disk, string $path, string $content): void;

    /**
     * Get the stored file
     *
     * @param string $disk
     * @param string $path
     * @return string
     */
    public function getFile(string $disk, string $path): string;
}
