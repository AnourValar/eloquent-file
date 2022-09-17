<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface;
use Illuminate\Http\Request;

trait ControllerProxyTrait
{
    /**
     * Retrieve (proxying) a file via user authorization
     *
     * @param Request $request
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
     * @param Request $request
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
