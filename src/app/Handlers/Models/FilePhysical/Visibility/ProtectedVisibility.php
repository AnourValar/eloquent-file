<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Http\UploadedFile;

class ProtectedVisibility extends PrivateVisibility implements DirectAccessInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface::directUrl()
     */
    public function directUrl(FilePhysical $filePhysical, string $generate = null): string
    {
        if (is_null($generate)) {
            throw new \LogicException('Direct access is not allowed for this file.');
        } else {
            $disk = $filePhysical->path_generate[$generate]['disk'];
            $path = $filePhysical->path_generate[$generate]['path'];
        }

        return url( \Storage::disk($disk)->url($path) );
    }
}
