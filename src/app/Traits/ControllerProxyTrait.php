<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyInterface;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;

trait ControllerProxyTrait
{
    /**
     * @see \Illuminate\Routing\Middleware\ValidateSignature::class
     * Downloading (proxying) a file
     *
     * @param Request $request
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return mixed
     */
    public function downloadFile(Request $request, \AnourValar\EloquentFile\FileVirtual $fileVirtual)
    {
        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyInterface) {
            return Response::deny(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $request->hasValidSignature()) {
            return Response::deny(trans('eloquent-file::auth.proxy.invalid'));
        }

        if (!$request->route('guest') && !$fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            return Response::deny(trans('eloquent-file::auth.proxy.not_authorized'));
        }

        return $visibilityHandler->download($fileVirtual);
    }

    /**
     * URL Generation
     *
     * @param Request $request
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param boolean $guest
     * @return mixed
     */
    public function generateFileUrl(Request $request, \AnourValar\EloquentFile\FileVirtual $fileVirtual, bool $guest = false)
    {
        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyInterface) {
            return Response::deny(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            return Response::deny(trans('eloquent-file::auth.proxy.not_authorized'));
        }

        return ['url' => $visibilityHandler->generateUrl($fileVirtual, $guest)];
    }
}
