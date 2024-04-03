<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Database\Eloquent\Model;

trait SeederTrait
{
    /**
     * Fake all disks
     *
     * @return self
     */
    protected function fakeStorages(): self
    {
        foreach (array_keys(config('filesystems.disks')) as $disk) {
            \Storage::fake($disk);
        }

        return $this;
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
            $mime = match (mb_strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
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

        $cache = \Cache::driver('array')->get(__METHOD__);
        $cacheKey = implode('|', [$file, $mime, get_class($entitable), $fileVirtual->name]);

        $fileVirtual->entity = $entitable->getMorphClass();
        $fileVirtual->entity_id = $entitable->getKey();
        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name, $entitable));

        if (isset($cache[$cacheKey])) {
            $fileVirtual->forceFill($cache[$cacheKey])->validate()->save();
            return;
        }

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class())->getConnectionName())->transaction(function () use (&$fileVirtual, $file, $mime) {
            \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload(
                new \Illuminate\Http\UploadedFile($file, basename($file), $mime, null, true),
                $fileVirtual
            );
        });

        $cache[$cacheKey] = $fileVirtual->only(['file_physical_id', 'filename', 'content_type']);
        \Cache::driver('array')->put(__METHOD__, $cache);
    }

    /**
     * Create a file from the buffer
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Database\Eloquent\Model $entitable
     * @param string $binary
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function createFromBuffer(FileVirtual $fileVirtual, Model $entitable, string $binary): FileVirtual
    {
        $fileVirtual->entity = $entitable->getMorphClass();
        $fileVirtual->entity_id = $entitable->getKey();
        $fileVirtual->forceFill($fileVirtual->getNameHandler()->generateFake($fileVirtual->entity, $fileVirtual->name, $entitable));

        $class = config('eloquent_file.models.file_physical');
        \DB::connection((new $class())->getConnectionName())->transaction(function () use ($fileVirtual, $binary) {
            $fileService = \App::make(\AnourValar\EloquentFile\Services\FileService::class);
            $fileService->upload($fileService->prepareFromBuffer($binary), $fileVirtual);
        });

        return $fileVirtual;
    }
}
