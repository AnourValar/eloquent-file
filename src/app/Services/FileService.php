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
     * @var array
     */
    private array $resources = [];

    /**
     * GC
     */
    public function __destruct()
    {
        foreach ($this->resources as $resource) {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /**
     * Create an UploadedFile from the buffer
     *
     * @param string $binary
     * @param string|null $fileName
     * @param string|null $mimeType
     * @return \Illuminate\Http\UploadedFile
     */
    public function prepareFromBuffer(string $binary, ?string $fileName = null, ?string $mimeType = null): UploadedFile
    {
        $resource = tmpfile();
        fwrite($resource, $binary);
        $fullPath = stream_get_meta_data($resource)['uri'];
        $this->resources[] = &$resource;

        return new UploadedFile(
            $fullPath,
            $fileName ?? basename($fullPath),
            $mimeType, // mimeType
            null, // error
            true // mark as test (since it's not a real HTTP request)
        );
    }

    /**
     * Create an UploadedFile from the file path
     *
     * @param string $fullPath
     * @param string|null $fileName
     * @param string|null $mimeType
     * @return \Illuminate\Http\UploadedFile
     */
    public function prepareFromPath(string $fullPath, ?string $fileName = null, ?string $mimeType = null): UploadedFile
    {
        return new UploadedFile(
            $fullPath,
            $fileName ?? basename($fullPath),
            $mimeType, // mimeType
            null, // error
            true // mark as test (since it's not a real HTTP request)
        );
    }

    /**
     * Upload a file
     *
     * @param \Illuminate\Http\UploadedFile|null $file
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string|null $fileValidationKey
     * @param callable|null $acl
     * @throws \RuntimeException
     * @return void
     */
    public function upload(?UploadedFile $file, FileVirtual &$fileVirtual, ?string $fileValidationKey = null, ?callable $acl = null): void
    {
        if (! is_null($fileVirtual->file_physical_id)) {
            throw new \RuntimeException('Attribute "file_physical_id" must be null.');
        }
        if ($fileVirtual->exists) {
            throw new \RuntimeException('FileVirtual must not be persisted.');
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

                    if ($file && $key === mb_strtolower($file->getClientOriginalExtension())) {
                        $type = $value;
                        break;
                    }
                }

                $title = isset($details['title']) ? trans($details['title']) : null;
            }
        }

        try {
            $this->handleUpload($fileVirtual, $file, $visibility, $type, $fileValidationKey, $title, $acl);
        } finally {
            foreach ($this->resources as $resource) {
                if (is_resource($resource)) {
                    fclose($resource);
                }
            }
            $this->resources = [];
        }
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
            $attributes = \Validator::make(
                $attributes,
                [
                    'entity' => ['required', 'string', 'min:1', 'max:200'],
                    'entity_id' => ['required', 'integer', 'min:1'],
                    'name' => ['required', 'string', 'min:1', 'max:200'],
                    'id' => ['nullable', 'not_empty', 'integer', 'min:1'],
                ]
            )->setAttributeNames((new $class())->getAttributeNames())->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new ValidationException($e->validator, $e->response, $e->errorBag, $prefix);
        }

        return $class::with('filePhysical')->where($attributes)->get();
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
        return tap(
            $fileVirtual
                ->replicate(array_merge(['entity', 'entity_id'], $fileVirtual->getComputed()))
                ->forceFill($data)
                ->validate($prefix)
        )->save();
    }

    /**
     * Get the lock
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @throws \RuntimeException
     * @return void
     */
    public function lock(FilePhysical $filePhysical): void
    {
        if (! isset($filePhysical->visibility, $filePhysical->type, $filePhysical->sha256)) {
            throw new \RuntimeException('Incorrect usage.');
        }

        \Atom::lockFilePhysical($filePhysical->visibility, $filePhysical->type, $filePhysical->sha256);
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param \Illuminate\Http\UploadedFile|null $file
     * @param mixed $visibility
     * @param mixed $type
     * @param string|null $fileValidationKey
     * @param string|null $title
     * @param callable|null $acl
     * @return void
     */
    private function handleUpload(
        FileVirtual &$fileVirtual,
        ?UploadedFile $file,
        $visibility,
        $type,
        ?string $fileValidationKey,
        ?string $title,
        ?callable $acl
    ): void {
        $class = config('eloquent_file.models.file_physical');
        $model = new $class();

        // Fill: visibility, type
        $model->visibility = $visibility;
        $model->type = $type;

        if (
            is_string($model->type) &&
            is_string($model->visibility) &&
            isset(config('eloquent_file.file_physical.type')[$model->type]) &&
            isset(config('eloquent_file.file_physical.visibility')[$model->visibility])
        ) {
            // Validation
            $this->validate($model, $file, $fileValidationKey, $title);

            // Fill: sha256, size, mime_type
            $model->sha256 = hash_file('sha256', $file->getRealPath());
            $model->size = $file->getSize();
            $model->mime_type = mb_strtolower($file->getClientMimeType());
            if (is_uploaded_file($file->getPathname()) || $model->mime_type == 'application/octet-stream') {
                $model->mime_type = mb_strtolower((string) $file->getMimeType()); // memory consume...
            }

            // Get the lock
            $this->lock($model);

            // Check file for uniqueness
            if ($model->getVisibilityHandler()->preventDuplicates()) {
                $check = $class::query()
                    ->where('visibility', '=', $model->visibility)
                    ->where('type', '=', $model->type)
                    ->where('sha256', '=', $model->sha256)
                    ->first();

                if ($check) {
                    $this->link($fileVirtual, $check, $file, $fileValidationKey, $acl);
                    return;
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
        $this->link($fileVirtual, $model, $file, $fileValidationKey, $acl);
        \Atom::onRollBack(
            fn () => $model->delete(), // for observers
            $model->getConnectionName()
        );

        // Store file
        if ($model->getVisibilityHandler() instanceof AdapterInterface) {
            $model->getVisibilityHandler()->putFile($file, $model);
        } else {
            $file->storeAs(dirname($model->path), basename($model->path), $model->disk); // stream write
        }

        // Side files generation
        if ($model->getTypeHandler() instanceof GenerateInterface) {
            $model->getTypeHandler()->dispatchGenerate($model);
        }
    }

    /**
     * @param FileVirtual $fileVirtual
     * @param FilePhysical $filePhysical
     * @param UploadedFile $file
     * @param string $fileValidationKey
     * @param callable $acl
     * @return void
     */
    private function link(
        FileVirtual &$fileVirtual,
        FilePhysical $filePhysical,
        UploadedFile $file,
        ?string $fileValidationKey,
        ?callable $acl
    ): void {
        $fileVirtual->file_physical_id = $filePhysical->id;

        if (is_null($fileVirtual->filename)) {
            $fileVirtual->filename = mb_substr($file->getClientOriginalName(), -100);
        }

        $fileVirtual->validate($fileValidationKey);
        if ($acl) {
            $acl($fileVirtual);
        }
        $fileVirtual->save();
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
        $extraRules = [];
        if ($filePhysical->type_details['rules_validate_mime_by_extension'] && $file?->getClientOriginalExtension()) {
            $extraRules[] = 'mimes:' . mb_strtolower($file->getClientOriginalExtension());
        }

        $validator = \Validator::make(
            ['file' => $file],
            ['file' => array_merge(['required', 'file', 'bail'], $filePhysical->type_details['rules'], $extraRules)]
        )->setAttributeNames(['file' => $title]);

        $passes = $validator->passes();
        if ($passes) {
            $validator = \Validator::make(['file' => $file], [])
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
