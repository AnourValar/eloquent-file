<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FileVirtual;

trait SeederTrait
{
    /**
     * Create an image
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $text
     * @return void
     */
    protected function createImage(FileVirtual &$fileVirtual, string $text = null): void
    {
        static $counter;
        if (is_null($text)) {
            $counter++;
            $text = $counter;
        }

        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name));

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class)->getConnectionName())->transaction(function () use ($fileVirtual, $text)
        {
            $fileName = tempnam(sys_get_temp_dir(), 'fake_');

            \Image
                ::canvas(1280, 720, '#FFFFFF')
                ->text($text, 650, 300, function ($font)
                {
                    $font->file(__DIR__.'/../../resources/arial.ttf');
                    $font->size(150);
                    $font->color('#FF0000');
                    $font->align('center');
                    $font->valign('top');
                })
                ->save($fileName, '80', 'jpg');

            \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload(
                new \Illuminate\Http\UploadedFile($fileName, $fileVirtual->name.'.jpg', 'image/jpeg', null, true),
                $fileVirtual
            );

            unlink($fileName);
        });
    }

    /**
     * Create a file from the list
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $path
     * @param array $files
     * @param string|null string $mime
     * @return void
     */
    protected function createFromList(FileVirtual &$fileVirtual, string $path, array $files, string $mime = null): void
    {
        $files = array_values($files);
        shuffle($files);
        $file = $path . $files[0];

        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name));

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class)->getConnectionName())->transaction(function () use ($fileVirtual, $file, $mime)
        {
            \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload(
                new \Illuminate\Http\UploadedFile($file, basename($file), $mime, null, true),
                $fileVirtual
            );
        });
    }
}
