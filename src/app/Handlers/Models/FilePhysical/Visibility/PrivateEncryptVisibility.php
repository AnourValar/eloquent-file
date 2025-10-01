<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

class PrivateEncryptVisibility extends PrivateVisibility implements AdapterInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface::putFile()
     */
    public function putFile(string $disk, string $path, string $content): void
    {
        $stringHelper = \App::make(\AnourValar\LaravelAtom\Helpers\StringHelper::class);

        \Storage::disk($disk)->put($path, $stringHelper->encryptBinary($content));
    }


    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface::getFile()
     */
    public function getFile(string $disk, string $path): string
    {
        $stringHelper = \App::make(\AnourValar\LaravelAtom\Helpers\StringHelper::class);

        return $stringHelper->decryptBinary(\Storage::disk($disk)->get($path));
    }
}
