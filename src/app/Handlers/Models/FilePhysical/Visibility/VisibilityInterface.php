<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Http\UploadedFile;

interface VisibilityInterface
{
    /**
     * Uniqueness of physical files
     *
     * @return bool
     */
    public function preventDuplicates(): bool;

    /**
     * Choose a disk
     *
     * @param array $disks
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    public function getDisk(array $disks, UploadedFile $file): string;

    /**
     * Choose a disk for the generated file
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param string $generate
     * @return string
     */
    public function getDiskForGenerated(FilePhysical $filePhysical, string $generate): string;

    /**
     * Choose a path (filename)
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    public function getPath(FilePhysical $filePhysical, UploadedFile $file): string;
}
