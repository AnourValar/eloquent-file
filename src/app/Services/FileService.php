<?php

namespace AnourValar\EloquentFile\Services;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface;
use AnourValar\EloquentValidation\Exceptions\ValidationException;
use Illuminate\Http\UploadedFile;

class FileService
{
    /**
     * Create UploadedFile from a buffer
     *
     * @param string $binary
     * @param string $fileName
     * @param string $mimeType
     * @return \Illuminate\Http\UploadedFile
     */
    public function prepareFromBuffer(string $binary, string $fileName = null, string $mimeType = null): UploadedFile
    {
        $temp = tmpfile();
        \App::terminating(function () use ($temp) {
            fclose($temp);
        });
        fwrite($temp, $binary);

        $fullPath = stream_get_meta_data($temp)['uri'];

        return new UploadedFile(
            $fullPath,
            $fileName ?? basename($fullPath),
            $mimeType, // mimeType
            null, // error
            true // mark as test (since it's not a real HTTP request)
        );
    }

    /**
     * Upload file and create a fileVirtual
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $fileValidationKey
     * @throws \LogicException
     * @return void
     */
    public function upload(?UploadedFile $file, FileVirtual &$fileVirtual, string $fileValidationKey = null): void
    {
        if (! is_null($fileVirtual->file_physical_id)) {
            throw new \LogicException('Attribute "file_physical_id" must be null.');
        }
        if ($fileVirtual->exists) {
            throw new \LogicException('FileVirtual must not be persisted.');
        }

        $visibility = null;
        $type = null;
        $title = null;
        if (is_string($fileVirtual->entity) && is_string($fileVirtual->name)) {
            $details = config("eloquent_file.file_virtual.entity.{$fileVirtual->entity}.name.{$fileVirtual->name}");
            if ($details) {
                $visibility = $details['visibility'];

                foreach ($details['types'] as $key => $value) {
                    if ($key == '*') {
                        $type = $value;
                        break;
                    }

                    if ($file
                        && (
                            ! mb_strlen($file->getClientOriginalExtension())
                            || $key === '.' . mb_strtolower($file->getClientOriginalExtension())
                        )
                        && $key === '.' . mb_strtolower($file->extension())
                    ) {
                        $type = $value;
                        break;
                    }
                }

                $title = isset($details['title']) ? trans($details['title']) : null;
            }
        }

        $filePhysical = $this->uploadPhysical($file, $visibility, $type, $fileValidationKey, $title);
        $fileVirtual->file_physical_id = $filePhysical->id;

        $this->link($fileVirtual, $file);
    }

    /**
     * Collect fileVirtuals
     *
     * @param array $attributes
     * @param mixed $prefix
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function collect(array $attributes, $prefix = null): \Illuminate\Database\Eloquent\Collection
    {
        $class = config('eloquent_file.models.file_virtual');

        try {
            $attributes = \Validator
                ::make(
                    $attributes,
                    [
                        'entity' => ['required', 'string', 'min:1', 'max:200'],
                        'entity_id' => ['required', 'integer', 'min:1'],
                        'name' => ['required', 'string', 'min:1', 'max:200'],
                        'id' => ['nullable', 'not_empty', 'integer', 'min:1'],
                    ]
                )
                ->setAttributeNames((new $class)->getAttributeNames())
                ->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new ValidationException($e->validator, $e->response, $e->errorBag, $prefix);
        }

        return $class::with('filePhysical')->where($attributes)->get();
    }

    /**
     * Delete fileVirtuals
     *
     * @param array|\Illuminate\Database\Eloquent\Model $collection
     * @param mixed $prefix
     * @return int
     */
    public function delete(array|\Illuminate\Database\Eloquent\Model $collection, $prefix = null): int
    {
        if (is_array($collection)) {
            $collection = $this->collect($collection, $prefix);
        } else {
            $class = config('eloquent_file.models.file_virtual');
            $collection = $class
                ::with('filePhysical')
                ->where('entity', '=', $collection->getMorphClass())
                ->where('entity_id', '=', $collection->getKey())
                ->cursor();
        }

        $counter = 0;
        foreach ($collection as $fileVirtual) {
            $this->lock($fileVirtual->filePhysical);
            $fileVirtual->validateDelete($prefix)->delete();

            $counter++;
        }

        return $counter;
    }

    /**
     * Replicate fileVirtual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param array $data
     * @param mixed $prefix
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    public function replicate(FileVirtual $fileVirtual, array $data, $prefix = null): FileVirtual
    {
        $this->lock($fileVirtual->filePhysical);

        $fileVirtual
            ->replicate(['entity', 'entity_id'])
            ->forceFill($data)
            ->validate($prefix)
            ->save();

        return $fileVirtual;
    }

    /**
     * Upload file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param mixed $visibility
     * @param mixed $type
     * @param string $fileValidationKey
     * @param string $title
     * @return \AnourValar\EloquentFile\FilePhysical
     */
    public function uploadPhysical(?UploadedFile $file, $visibility, $type, string $fileValidationKey = null, string $title = null): FilePhysical
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
            $this->validate($model, $file, $fileValidationKey, $title);

            // Fill: sha256, size, mime_type
            $model->sha256 = hash_file('sha256', $file->getRealPath());
            $model->size = $file->getSize();
            $model->mime_type = mb_strtolower((string) $file->getMimeType());

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
            $model->disk = $model->getVisibilityHandler()->getDisk($disks, $file);

            // Fill: path
            $model->path = $model->getVisibilityHandler()->getPath($model, $file);
        }

        // Validation & save
        $model->validate($fileValidationKey)->save();

        // Store file
        if ($model->getVisibilityHandler() instanceof AdapterInterface) {
            $model->getVisibilityHandler()->putFile($file, $model);
        } else {
            $file->storeAs(dirname($model->path), basename($model->path), $model->disk);
        }

        \Atom::onRollBack(
            function () use ($model) {
                $model->delete(); // for observers
            },
            $model->getConnectionName()
        );

        // Side File Generation
        if ($model->getTypeHandler() instanceof GenerateInterface) {
            \Atom::onCommit(
                function () use ($model) {
                    $model->getTypeHandler()->dispatch($model);
                },
                $model->getConnectionName()
            );
        }

        return $model;
    }

    /**
     * Refill and create a fileVirtual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Http\UploadedFile $file
     * @return void
     */
    public function link(FileVirtual &$fileVirtual, UploadedFile $file = null): void
    {
        // Get the lock
        $this->lock($fileVirtual->filePhysical);

        // Refill
        if ($file) {
            if (is_null($fileVirtual->filename)) {
                $fileVirtual->filename = mb_substr($file->getClientOriginalName(), -100);
            }

            if (is_null($fileVirtual->content_type)) {
                $fileVirtual->content_type = mb_strtolower((string) $file->getMimeType());
            }
        }

        // Validation & save
        $fileVirtual->validate()->save();
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
            throw new \LogicException('Incorrect usage.');
        }

        \Atom::lockFilePhysical($filePhysical->visibility, $filePhysical->type, $filePhysical->sha256);
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $fileValidationKey
     * @param string $title
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     * @return void
     */
    private function validate(FilePhysical $filePhysical, ?UploadedFile $file, ?string $fileValidationKey, ?string $title): void
    {
        $validator = \Validator
            ::make(
                ['file' => $file],
                ['file' => array_merge(['required', 'file', 'bail'], $filePhysical->type_details['rules'])]
            )
            ->setAttributeNames(['file' => $title]);

        $passes = $validator->passes();
        if ($passes) {
            $validator = \Validator
                ::make(['file' => $file], [])
                ->setAttributeNames(['file' => $title])
                ->after(function ($validator) use ($filePhysical) {
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
            throw (new ValidationException($validator))->replaceKey('file', $fileValidationKey);
        }
    }
}
