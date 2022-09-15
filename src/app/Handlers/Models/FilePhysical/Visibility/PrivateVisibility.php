<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Http\UploadedFile;

class PrivateVisibility implements VisibilityInterface, ProxyAccessInterface
{
    /**
     * @var string
     */
    public const METHOD_SIGNED = 'signed';
    public const METHOD_SIGNED_DIRECT = 'signed_direct';
    public const METHOD_AUTHORIZE = 'authorize';

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::preventDuplicates()
     */
    public function preventDuplicates(): bool
    {
        return true; // if it's false, getPath must provide a unique name for the same file
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getDisk()
     */
    public function getDisk(array $disks, UploadedFile $file): string
    {
        shuffle($disks);

        return $disks[0];
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::getPath()
     */
    public function getPath(FilePhysical $filePhysical, UploadedFile $file): string
    {
        if (empty($filePhysical->sha256)) {
            throw new \LogicException('Incorrect usage.');
        }

        return $filePhysical->type.'_'.$filePhysical->visibility.'/'
            .mb_substr($filePhysical->sha256, 0, 2).'/'
            .mb_substr($filePhysical->sha256, 2, 2).'/'
            .mb_substr($filePhysical->sha256, 4, 2).'/'
            .$filePhysical->sha256;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyUrl()
     */
    public function proxyUrl(FileVirtual $fileVirtual): string
    {
        $route = $fileVirtual->filePhysical->visibility_details['proxy_route'];
        $method = $fileVirtual->filePhysical->visibility_details['proxy_route_method'];

        if ($method === static::METHOD_SIGNED) {
            return \URL::temporarySignedRoute(
                $route,
                now()->addMinutes($this->expireIn($fileVirtual)),
                ['file_virtual' => $fileVirtual->id, 'file_name' => $this->getFileName($fileVirtual)]
            );
        }

        if ($method === static::METHOD_SIGNED_DIRECT) {
            if (! \Storage::disk($fileVirtual->filePhysical->disk)->providesTemporaryUrls()) {
                throw new \LogicException('Disk driver does not support temporary urls.');
            }

            return url(
                \Storage
                    ::disk($fileVirtual->filePhysical->disk)
                    ->temporaryUrl(
                        $fileVirtual->filePhysical->path,
                        now()->addMinutes($this->expireIn($fileVirtual)),
                        ['ResponseContentDisposition' => 'inline; filename='.$this->getFileName($fileVirtual)]
                    )
            );
        }

        if ($method === static::METHOD_AUTHORIZE) {
            return route($route, ['file_virtual' => $fileVirtual->id, 'file_name' => $this->getFileName($fileVirtual)]);
        }

        throw new \LogicException('Option "proxy_route_method" must be set properly.');
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyDownload()
     */
    public function proxyDownload(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response
    {
        return $this->proxy($fileVirtual, 'attachment');
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyInline()
     */
    public function proxyInline(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response
    {
        return $this->proxy($fileVirtual, 'inline');
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $disposition
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function proxy(FileVirtual $fileVirtual, string $disposition): \Symfony\Component\HttpFoundation\Response
    {
        $visibilityHandler = $fileVirtual->filePhysical->getVisibilityHandler();

        if ($visibilityHandler instanceof \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface) {
            return response()->streamDownload(
                function () use (&$visibilityHandler, &$fileVirtual) {
                    echo $visibilityHandler->getFile($fileVirtual->filePhysical);
                },
                $this->getFileName($fileVirtual),
                ['Content-Type' => $fileVirtual->content_type],
                $disposition
            );
        }

        return \Storage
            ::disk($fileVirtual->filePhysical->disk)
            ->response(
                $fileVirtual->filePhysical->path,
                $this->getFileName($fileVirtual),
                ['Content-Type' => $fileVirtual->content_type],
                $disposition
            );
    }

    /**
     * Returns the number of minutes during which the url will be actual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return int
     */
    protected function expireIn(FileVirtual $fileVirtual): int
    {
        return ceil($fileVirtual->filePhysical->size / (1024 * 1024)) + 10;
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return string
     */
    protected function getFileName(FileVirtual $fileVirtual): string
    {
        $pathInfo = pathinfo($fileVirtual->filename);
        if (isset($pathInfo['extension']) && mb_strlen($pathInfo['extension'])) {
            $fileName = \Str::slug($pathInfo['filename']) . '.' . \Str::slug($pathInfo['extension']);
        } else {
            $fileName = \Str::slug($pathInfo['filename']);
        }

        return $fileName;
    }
}
