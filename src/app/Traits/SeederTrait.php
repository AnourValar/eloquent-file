<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FilePhysical;

trait SeederTrait
{
    /**
     * Create an image
     *
     * @param string $visibility
     * @param string $type
     * @param string $text
     * @return \AnourValar\EloquentFile\FilePhysical
     */
    protected function createImage(string $visibility, string $type, string $text = null): FilePhysical
    {
        static $counter;

        if (is_null($text)) {
            $counter++;
            $text = $counter;
        }

        $class = config('eloquent_file.models.file_physical');
        return \DB::connection((new $class)->getConnectionName())->transaction(function () use ($text, $visibility, $type)
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

            return \App::make(\AnourValar\EloquentFile\Services\FileService::class)->uploadPhysical(
                new \Illuminate\Http\UploadedFile($fileName, \Str::slug($text).'.jpg', 'image/jpeg', null, true),
                $visibility,
                $type
            );
        });
    }
}
