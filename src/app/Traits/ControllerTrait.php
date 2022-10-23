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
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $fileVirtual->getEntityHandler()->canAccess($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.not_authorized'));
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
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $request->hasValidSignature()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.invalid'));
        }

        if ($download) {
            return $visibilityHandler->proxyDownload($fileVirtual);
        }
        return $visibilityHandler->proxyInline($fileVirtual);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function extractFileVirtualFrom(Request $request): \AnourValar\EloquentFile\FileVirtual
    {
        $fileVirtual = $request->route('file_virtual');
        if (is_scalar($fileVirtual)) {
            $class = config('eloquent_file.models.file_virtual');
            $fileVirtual = $class::findOrFail((int) $fileVirtual);
        }

        return $fileVirtual;
    }

    /**
     * Upload a file
     *
     * @param \Illuminate\Http\Request $request
     * @param mixed $data
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    protected function uploadFileFrom(Request $request, mixed $data = []): \AnourValar\EloquentFile\FileVirtual
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
            $data
        );

        $fileVirtual = (new \App\FileVirtual)->forceFill($data);


        // Request
        $files = $request->file();

        if (! count($files)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.file_missed'));
        }

        if (count($files) > 1) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.file_multi'));
        }


        // Upload
        $key = array_key_last($files);
        \App::make(\AnourValar\EloquentFile\Services\FileService::class)->upload($files[$key], $fileVirtual, $key);

        return $fileVirtual;
    }
}
