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
        $stringHelper = \App::make(\AnourValar\LaravelAtom\Helpers\StringHelper::class);

        \Storage::disk($filePhysical->disk)->put($filePhysical->path, $stringHelper->encryptBinary($file->getContent()));
    }


    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface::getFile()
     */
    public function getFile(FilePhysical $filePhysical): string
    {
        $stringHelper = \App::make(\AnourValar\LaravelAtom\Helpers\StringHelper::class);

        return $stringHelper->decryptBinary(\Storage::disk($filePhysical->disk)->get($filePhysical->path));
    }
}
