<?php

namespace AnourValar\EloquentFile\Services;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentValidation\Exceptions\ValidationException;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface;
use Illuminate\Http\UploadedFile;

class FileService
{
    /**
     * Upload file and create virtual file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $fileValidationKey
     * @throws \LogicException
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    public function upload(?UploadedFile $file, FileVirtual $fileVirtual, string $fileValidationKey = null): FileVirtual
    {
        if (! is_null($fileVirtual->file_physical_id)) {
            throw new \LogicException('Attribute "file_physical_id" must be null.');
        }

        $visibility = null;
        $type = null;
        if (is_string($fileVirtual->entity) && is_string($fileVirtual->name)) {
            $details = config("eloquent_file.file_virtual.entity.{$fileVirtual->entity}.name.{$fileVirtual->name}");
            if ($details) {
                $visibility = $details['visibility'];
                $type = $details['type'];
            }
        }

        $filePhysical = $this->uploadPhysical($file, $visibility, $type, $fileValidationKey);
        $fileVirtual->file_physical_id = $filePhysical->id;

        return $this->link($fileVirtual, $file);
    }

    /**
     * Upload file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param mixed $visibility
     * @param mixed $type
     * @param string $fileValidationKey
     * @return \AnourValar\EloquentFile\FilePhysical
     */
    public function uploadPhysical(?UploadedFile $file, $visibility, $type, string $fileValidationKey = null): FilePhysical
    {
        $class = config('eloquent_file.models.file_physical');
        $model = new $class;

        // Fill: visibility, type
        $model->visibility = $visibility;
        $model->type = $type;

        if (is_string($model->type) &&
            is_string($model->visibility) &&
            isset(config('eloquent_file.file_physical.type')[$model->type]) &&
            isset(config('eloquent_file.file_physical.visibility')[$model->visibility])
        ) {
            // Validation
            $this->validate($model, $file, $fileValidationKey);

            // Fill: sha256, size, mime_type
            $model->sha256 = hash_file('sha256', $file->getRealPath());
            $model->size = $file->getSize();
            $model->mime_type = $file->getMimeType();

            // Get the lock
            $this->lock($model);

            // Check file for uniqueness
            if ($model->getVisibilityHandler()->preventDuplicates()) {
                $check = $class
                    ::where('visibility', '=', $model->visibility)
                    ->where('type', '=', $model->type)
                    ->where('sha256', '=', $model->sha256)
                    ->first();

                if ($check) {
                    return $check;
                }
            }

            // Technical create
            $model->save();

            // Fill: disk
            $disks = config('eloquent_file.file_physical.visibility')[$model->visibility]['disks'];
            if (! is_array($disks)) {
                $disks = explode(',', $disks);
                $disks = array_map('trim', $disks);
            }
            $model->disk = $model->getVisibilityHandler()->getDisk($disks, $file);

            // Fill: path
            $model->path = $model->getVisibilityHandler()->getPath($model, $file);
        }

        // Validation & save
        $model->validate()->save();

        // File move
        $file->storeAs(dirname($model->path), basename($model->path), $model->disk);
        \Atom::onRollBack(
            function () use ($model)
            {
                $model->delete(); // for observers
            },
            $model->getConnectionName()
        );

        // Side File Generation
        if ($model->getTypeHandler() instanceof GenerateInterface) {
            \Atom::onCommit(
                function () use ($model)
                {
                    \AnourValar\EloquentFile\Jobs\FilePhysicalGenerateJob::dispatch($model);
                },
                $model->getConnectionName()
            );
        }

        return $model;
    }

    /**
     * Refill and create fileVirtual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Http\UploadedFile $file
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    public function link(FileVirtual $fileVirtual, UploadedFile $file = null): FileVirtual
    {
        // Get the lock
        $this->lock($fileVirtual->physical);

        // Refill
        if ($file) {
            if (is_null($fileVirtual->filename)) {
                $fileVirtual->filename = mb_substr($file->getClientOriginalName(), -100);
            }

            if (is_null($fileVirtual->content_type)) {
                $fileVirtual->content_type = $file->getMimeType(); //$file->getClientMimeType();
            }
        }

        // Validation & save
        $fileVirtual->validate()->save();

        return $fileVirtual;
    }

    /**
     * Get the lock
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @throws \LogicException
     * @return void
     */
    public function lock(?FilePhysical $filePhysical): void
    {
        if (! $filePhysical) {
            return;
        }

        if (! isset($filePhysical->visibility, $filePhysical->type, $filePhysical->sha256)) {
            throw new \LogicException();
        }

        \Atom::lockFilePhysical($filePhysical->visibility, $filePhysical->type, $filePhysical->sha256);
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileValidationKey
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     * @return void
     */
    private function validate(FilePhysical $filePhysical, ?UploadedFile $file, ?string $fileValidationKey): void
    {
        $validator = \Validator::make(
            ['file' => $file],
            ['file' => array_merge(['required', 'file'], $filePhysical->type_details['rules'])]
        );

        $passes = $validator->passes();
        if ($passes) {
            $validator = \Validator
                ::make(['file' => $file], [])
                ->after(function ($validator) use ($filePhysical)
                {
                    static $triggered;

                    if (! $triggered) {
                        $triggered = true;

                        $filePhysical->getTypeHandler()->validate($filePhysical->type_details, $validator);
                    }
                });

            $passes = $validator->passes();
            if ($passes && $validator->getRules()) {
                $passes = $validator->passes();
            }
        }

        if (! $passes) {
            throw new ValidationException($validator, null, 'default', $fileValidationKey, true);
        }
    }
}
