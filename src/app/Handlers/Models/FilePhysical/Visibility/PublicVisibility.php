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
    public function getDisk(array $disks, UploadedFile $file): string
    {
        shuffle($disks);

        return $disks[0];
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getDiskForGenerated()
     */
    public function getDiskForGenerated(FilePhysical $filePhysical, string $generate): string
    {
        return $filePhysical->disk;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getPath()
     */
    public function getPath(FilePhysical $filePhysical, UploadedFile $file): string
    {
        if (empty($filePhysical->sha256)) {
            throw new \LogicException('Incorrect usage.');
        }

        $extension = $file->getClientOriginalExtension();
        //if (! mb_strlen($extension)) {
        //     $extension = $file->extension();
        //}
        if (mb_strlen($extension)) {
            $extension = ".$extension";
        }

        return $filePhysical->type.'_'.$filePhysical->visibility.'/'
            .mb_substr($filePhysical->sha256, 0, 2).'/'
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
    public function directUrl(FilePhysical $filePhysical, ?string $generate = null): ?string
    {
        if (is_null($generate)) {
            $disk = $filePhysical->disk;
            $path = $filePhysical->path;
        } else {
            $disk = $filePhysical->path_generate[$generate]['disk'];
            $path = $filePhysical->path_generate[$generate]['path'];
        }

        return url(\Storage::disk($disk)->url($path));
    }
}
