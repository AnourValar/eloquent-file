<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface;
use Illuminate\Http\Request;

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
    public function proxyUserAuthorize(Request $request, bool $download = true)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyAccessInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        if (! $fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.not_authorized'));
        }

        if ($download) {
            return $visibilityHandler->proxyDownload($fileVirtual);
        }
        return $visibilityHandler->proxyInline($fileVirtual);
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
    public function proxyUrlSigned(Request $request, bool $download = true)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyAccessInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.unsupported'));
        }

        if (! $request->hasValidSignature()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.download.invalid'));
        }

        if ($download) {
            return $visibilityHandler->proxyDownload($fileVirtual);
        }
        return $visibilityHandler->proxyInline($fileVirtual);
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
        $acl = function ($fileVirtual) use ($request) {
            if (! $fileVirtual->getEntityHandler()->canUpload($fileVirtual, $request->user())) {
                throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.upload.not_authorized'));
            }
        };
        \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload($files[$key], $fileVirtual, $key, $acl);

        return $fileVirtual;
    }

    /**
     * Delete a file
     *
     * @param \Illuminate\Http\Request $request
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function deleteFileFrom(Request $request): \AnourValar\EloquentFile\FileVirtual
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        if (! $fileVirtual->getEntityHandler()->canDelete($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.delete.not_authorized'));
        }

        $fileVirtual->validateDelete()->delete();
        return $fileVirtual;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function extractFileVirtualFrom(Request $request): \AnourValar\EloquentFile\FileVirtual
    {
        $id = ($request->route('file_virtual') ?? $request->input('file_virtual_id'));
        $class = config('eloquent_file.models.file_virtual');

        return $class::findOrFail((int) is_scalar($id) ? $id : 0);
    }
}
