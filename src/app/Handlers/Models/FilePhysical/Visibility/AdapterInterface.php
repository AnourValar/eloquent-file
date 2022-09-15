<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;

interface AdapterInterface
{
    /**
     * Store the uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function putFile(\Illuminate\Http\UploadedFile $file, FilePhysical $filePhysical): void;

    /**
     * Get the stored file
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return string
     */
    public function getFile(FilePhysical $filePhysical): string;
}
