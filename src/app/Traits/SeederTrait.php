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
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function createImage(FileVirtual $fileVirtual, string $text = null): FileVirtual
    {
        static $counter;

        if (is_null($text)) {
            $counter++;
            $text = $counter;
        }

        $class = config('eloquent_file.models.file_physical');
        return \DB::connection((new $class)->getConnectionName())->transaction(function () use ($fileVirtual, $text)
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

            $fileVirtual = \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload(
                new \Illuminate\Http\UploadedFile($fileName, $fileVirtual->name.'.jpg', 'image/jpeg', null, true),
                $fileVirtual
            );

            unlink($fileName);
            return $fileVirtual;
        });
    }
}
