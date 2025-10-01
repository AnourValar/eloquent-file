<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Http\UploadedFile;

class PublicVisibility implements VisibilityInterface, DirectAccessInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::preventDuplicates()
     */
    public function preventDuplicates(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getDisk()
     */
    public function getDisk(array $disks): string
    {
        shuffle($disks);

        return $disks[0];
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getPath()
     */
    public function getPath(FilePhysical $filePhysical, UploadedFile $file): string
    {
        if (! isset($filePhysical->sha256, $filePhysical->id)) {
            throw new \LogicException('Incorrect usage.');
        }

        $extension = $file->getClientOriginalExtension();
        //if (! mb_strlen($extension)) {
        //     $extension = $file->extension();
        //}
        if (mb_strlen($extension)) {
            $extension = ".$extension";
        }

        return mb_substr($filePhysical->sha256, 0, 2).'/'
            .mb_substr($filePhysical->sha256, 2, 2).'/'
            .mb_substr($filePhysical->sha256, 4, 2).'/'
            .$filePhysical->sha256
            .$filePhysical->id // important!
            .$extension;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface::directUrl()
     */
    public function directUrl(string $disk, string $path): string
    {
        return url(\Storage::disk($disk)->url($path));
    }
}
