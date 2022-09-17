<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Http\UploadedFile;

class PrivateEncryptVisibility extends PrivateVisibility implements AdapterInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface::putFile()
     */
    public function putFile(\Illuminate\Http\UploadedFile $file, FilePhysical $filePhysical): void
    {
        \Storage::disk($filePhysical->disk)->put($filePhysical->path, encrypt($file->getContent()));
    }


    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface::getFile()
     */
    public function getFile(FilePhysical $filePhysical): string
    {
        return decrypt(\Storage::disk($filePhysical->disk)->get($filePhysical->path));
    }
}
