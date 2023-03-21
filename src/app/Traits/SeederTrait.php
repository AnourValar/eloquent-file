<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Database\Eloquent\Model;

trait SeederTrait
{
    /**
     * Create an image
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Database\Eloquent\Model $entitable
     * @param string $text
     * @return void
     */
    protected function createImage(FileVirtual &$fileVirtual, Model $entitable, string $text = null): void
    {
        static $counter;
        if (is_null($text)) {
            $counter++;
            $text = $counter;
        }

        $fileVirtual->entity = $entitable->getMorphClass();
        $fileVirtual->entity_id = $entitable->getKey();
        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name, $entitable));

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class)->getConnectionName())->transaction(function () use ($fileVirtual, $text) {
            $fileName = tempnam(sys_get_temp_dir(), 'fake_');

            \Image
                ::canvas(1280, 720, '#FFFFFF')
                ->text($text, 650, 300, function ($font) {
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
     * @param \Illuminate\Database\Eloquent\Model $entitable
     * @param string $path
     * @param array $files
     * @param string|null string $mime
     * @return void
     */
    protected function createFromList(
        FileVirtual &$fileVirtual,
        Model $entitable,
        string $path,
        array $files = [],
        string $mime = null
    ): void {
        static $cache;
        static $cacheFiles;

        if (! $files) {
            if (! isset($cacheFiles[$path])) {
                foreach (scandir($path) as $item) {
                    if (! in_array($item, ['.', '..', '.gitignore']) && stripos($item, '.')) {
                        $cacheFiles[$path][] = $item;
                    }
                }
            }

            $files = $cacheFiles[$path];
        }

        $files = array_values($files);
        shuffle($files);
        $file = $path . $files[0];

        if (! $mime) {
            $mime = match( mb_strtolower(pathinfo($file, PATHINFO_EXTENSION)) ) {
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'pdf' => 'application/pdf',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'rar' => 'application/vnd.rar',
                'zip' => 'application/zip',
                default => null,
            };
        }
        $cacheKey = implode('|', [$file, $mime, get_class($entitable), $fileVirtual->name]);

        $fileVirtual->entity = $entitable->getMorphClass();
        $fileVirtual->entity_id = $entitable->getKey();
        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name, $entitable));

        if (isset($cache[$cacheKey])) {
            $fileVirtual->forceFill($cache[$cacheKey])->validate()->save();
            return;
        }

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class)->getConnectionName())->transaction(function () use ($fileVirtual, $file, $mime) {
            \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload(
                new \Illuminate\Http\UploadedFile($file, basename($file), $mime, null, true),
                $fileVirtual
            );
        });

        $cache[$cacheKey] = $fileVirtual->only(['file_physical_id', 'filename', 'content_type']);
    }
}
