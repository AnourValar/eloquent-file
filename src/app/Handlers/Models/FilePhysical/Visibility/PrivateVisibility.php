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
                ['file_virtual' => $fileVirtual->id]
            );
        }

        if ($method === static::METHOD_AUTHORIZE) {
            return route($route, ['file_virtual' => $fileVirtual->id]);
        }

        throw new \LogicException('Option "proxy_route_method" must be set properly.');
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyDownload()
     */
    public function proxyDownload(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response
    {
        return \Storage
            ::disk($fileVirtual->filePhysical->disk)
            ->download(
                $fileVirtual->filePhysical->path,
                $this->getFileName($fileVirtual),
                ['Content-Type' => $fileVirtual->content_type]
            );
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyInline()
     */
    public function proxyInline(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response
    {
        return \Storage
            ::disk($fileVirtual->filePhysical->disk)
            ->response(
                $fileVirtual->filePhysical->path,
                $this->getFileName($fileVirtual),
                ['Content-Type' => $fileVirtual->content_type]
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
