<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface;
use Illuminate\Http\Request;
use AnourValar\EloquentFile\FileVirtual;

trait ControllerTrait
{
    /**
     * Retrieve (proxying) a file via user authorization
     *
     * @param \Illuminate\Http\Request $request
     * @param bool $download
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function proxyUserAuthorize(Request $request, bool $download = false)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        if (! $fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.not_authorized'));
        }

        $generate = $request->input('generate');
        if ($generate && (! is_string($generate) || ! isset($fileVirtual->filePhysical->path_generate[$generate]))) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        $visibilityHandler = $generate ? $fileVirtual->filePhysical->path_generate[$generate]['visibility'] : $fileVirtual->filePhysical->visibility;
        $visibilityHandler = \App::make(config("eloquent_file.file_physical.visibility.{$visibilityHandler}.bind"));
        if (! $visibilityHandler instanceof ProxyAccessInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        return $this->proxy($fileVirtual, $request->route('filename'), $generate, $download ? 'attachment' : 'inline');
    }

    /**
     * Retrieve (proxying) a file via signed url
     * @see \Illuminate\Routing\Middleware\ValidateSignature::class
     *
     * @param \Illuminate\Http\Request $request
     * @param bool $download
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function proxyUrlSigned(Request $request, bool $download = false)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        if (! $request->hasValidSignature()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.invalid'));
        }

        $generate = $request->input('generate');
        if ($generate && (! is_string($generate) || ! isset($fileVirtual->filePhysical->path_generate[$generate]))) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        $visibilityHandler = $generate ? $fileVirtual->filePhysical->path_generate[$generate]['visibility'] : $fileVirtual->filePhysical->visibility;
        $visibilityHandler = \App::make(config("eloquent_file.file_physical.visibility.{$visibilityHandler}.bind"));
        if (! $visibilityHandler instanceof ProxyAccessInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        return $this->proxy($fileVirtual, $request->route('filename'), $generate, $download ? 'attachment' : 'inline');
    }

    /**
     * Upload a file
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $data
     * @param mixed $extraData
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function uploadFileFrom(Request $request, $data = [], $extraData = []): \AnourValar\EloquentFile\FileVirtual
    {
        // FileVirtual
        if ($data instanceof \Illuminate\Database\Eloquent\Model) {
            $data = [
                'entity' => $data->getMorphClass(),
                'entity_id' => $data->getKey(),
            ];
        }

        $data = array_replace(
            [
                'entity' => ($request->route('entity') ?? $request->input('entity')),
                'entity_id' => ($request->route('entity_id') ?? $request->input('entity_id')),
                'name' => ($request->route('name') ?? $request->input('name')),
                'title' => $request->input('title'),
                'details' => $request->input('details'),
            ],
            $data,
            $extraData
        );

        $class = config('eloquent_file.models.file_virtual');
        $fileVirtual = (new $class())->forceFill($data);


        // Request
        $files = $request->file();

        if (! count($files)) {
            throw new \AnourValar\EloquentValidation\Exceptions\ValidationException(trans('eloquent-file::auth.upload.file_missed'));
        }

        if (count($files) > 1) {
            throw new \AnourValar\EloquentValidation\Exceptions\ValidationException(trans('eloquent-file::auth.upload.file_multi'));
        }

        $key = array_key_last($files);


        // Upload
        $fileVirtual->getEntityHandler()->lockOnChange($fileVirtual);
        $acl = function ($fileVirtual) use ($request) {
            if (! $fileVirtual->getEntityHandler()->canUpload($fileVirtual, $request->user())) {
                throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.upload.not_authorized'));
            }
        };
        \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload($files[$key], $fileVirtual, $key, $acl);

        return $fileVirtual;
    }

    /**
     * Download a file (in a 2 steps)
     *
     * @param string $url
     * @param array $data
     * @param string|null $validationKey
     * @throws \AnourValar\EloquentValidation\Exceptions\ValidationException
     * @return callable
     */
    protected function downloadFileFrom(string $url, array $data, ?string $validationKey = null): callable
    {
        // Handle with input
        $filename = mb_substr(basename(parse_url($url)['path'] ?? sha1($url)), -100);
        $data = array_replace(['filename' => $filename], $data);


        // Request
        if (preg_match('#^https?\:\/\/#u', $url)) {
            $file = $this->downloadProcedure($url);
        } else {
            $file = false;
        }

        if ($file === false) {
            throw new \AnourValar\EloquentValidation\Exceptions\ValidationException(trans('eloquent-file::auth.upload.file_missed'));
        }

        $fileService = \App::make(\AnourValar\EloquentFile\Services\FileService::class);
        $file = $fileService->prepareFromBuffer($file, $data['filename']);


        // Closure to upload
        return function () use ($data, $file, $validationKey, $fileService) {
            $class = config('eloquent_file.models.file_virtual');
            $fileVirtual = (new $class())->forceFill($data);

            $fileVirtual->getEntityHandler()->lockOnChange($fileVirtual);
            $acl = function ($fileVirtual) {
                if (! $fileVirtual->getEntityHandler()->canUpload($fileVirtual, \Auth::user())) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.upload.not_authorized'));
                }
            };
            $fileService->upload($file, $fileVirtual, $validationKey, $acl);

            return $fileVirtual;
        };
    }

    /**
     * @param string $url
     * @return string|false
     */
    protected function downloadProcedure(string $url): string|false
    {
        return @file_get_contents($url);
    }

    /**
     * Delete a file
     *
     * @param \Illuminate\Http\Request $request
     * @param array $where
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function deleteFileFrom(Request $request, array $where = []): \AnourValar\EloquentFile\FileVirtual
    {
        $fileVirtual = $this->extractFileVirtualFrom($request, $where);

        $fileVirtual->getEntityHandler()->lockOnChange($fileVirtual);
        if (! $fileVirtual->getEntityHandler()->canDelete($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.delete.not_authorized'));
        }

        $fileVirtual->validateDelete()->delete();
        return $fileVirtual;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param array $where
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function extractFileVirtualFrom(Request $request, array $where = []): \AnourValar\EloquentFile\FileVirtual
    {
        $id = ($request->route('file_virtual') ?? $request->input('file_virtual_id'));
        $class = config('eloquent_file.models.file_virtual');

        return $class::where($where)->findOrFail((int) is_scalar($id) ? $id : 0);
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $filename
     * @param string|null $generate
     * @param string $disposition
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function proxy(FileVirtual $fileVirtual, string $filename, ?string $generate, string $disposition): \Symfony\Component\HttpFoundation\Response
    {
        if ($generate) {
            $visibility = $fileVirtual->filePhysical->path_generate[$generate]['visibility'];
            $disk = $fileVirtual->filePhysical->path_generate[$generate]['disk'];
            $path = $fileVirtual->filePhysical->path_generate[$generate]['path'];
            $mimeType = $fileVirtual->filePhysical->path_generate[$generate]['mime_type'];
        } else {
            $visibility = $fileVirtual->filePhysical->visibility;
            $disk = $fileVirtual->filePhysical->disk;
            $path = $fileVirtual->filePhysical->path;
            $mimeType = $fileVirtual->filePhysical->mime_type;
        }

        $visibilityHandler = \App::make(config("eloquent_file.file_physical.visibility.{$visibility}.bind"));
        $headers = ['Content-Type' => $mimeType, 'Cache-Control' => 'public, max-age=86400'];

        if ($visibilityHandler instanceof \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface) {
            return response()->streamDownload(
                function () use (&$visibilityHandler, $disk, $path) {
                    echo $visibilityHandler->getFile($disk, $path);
                },
                $filename,
                $headers,
                $disposition
            );
        }

        return \Storage::disk($disk)->response($path, $filename, $headers, $disposition);
    }
}
