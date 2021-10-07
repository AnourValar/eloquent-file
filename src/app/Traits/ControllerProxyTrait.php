<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyInterface;
use Illuminate\Http\Request;

trait ControllerProxyTrait
{
    /**
     * Downloading (proxying) a file via authorization
     *
     * @param Request $request
     * @param boolean $download
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authorizedProxy(Request $request, bool $download = true)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.not_authorized'));
        }

        if ($download) {
            return $visibilityHandler->download($fileVirtual);
        }
        return $visibilityHandler->inline($fileVirtual);
    }

    /**
     * Downloading (proxying) a file via signed url
     * @see \Illuminate\Routing\Middleware\ValidateSignature::class
     *
     * @param Request $request
     * @param boolean $download
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function guestProxy(Request $request, bool $download = true)
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $request->hasValidSignature()) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.invalid'));
        }

        if ($download) {
            return $visibilityHandler->download($fileVirtual);
        }
        return $visibilityHandler->inline($fileVirtual);
    }

    /**
     * Generates a temporary link for downloading
     *
     * @param Request $request
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @return string
     */
    public function guestUrl(Request $request): string
    {
        $fileVirtual = $this->extractFileVirtualFrom($request);

        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();
        if (! $visibilityHandler instanceof ProxyInterface) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.unsupported'));
        }

        if (! $fileVirtual->getEntityHandler()->canDownload($fileVirtual, $request->user())) {
            throw new \Illuminate\Auth\Access\AuthorizationException(trans('eloquent-file::auth.proxy.not_authorized'));
        }

        return $visibilityHandler->generateUrl($fileVirtual);
    }

    /**
     * @param Request $request
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
}
