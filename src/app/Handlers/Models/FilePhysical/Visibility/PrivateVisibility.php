<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Http\UploadedFile;

class PrivateVisibility implements VisibilityInterface, ProxyAccessInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface::preventDuplicates()
     */
    public function preventDuplicates(): bool
    {
        return true;
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

        return $filePhysical->type.'/'
            .mb_substr($filePhysical->sha256, 0, 2).'/'
            .mb_substr($filePhysical->sha256, 2, 2).'/'
            .mb_substr($filePhysical->sha256, 4, 2).'/'
            .$filePhysical->sha256;
    }

    /**
     * @see \Illuminate\Routing\Middleware\ValidateSignature::class
     *
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyUrl()
     */
    public function proxyUrl(FileVirtual $fileVirtual): string
    {
        $route = $fileVirtual->filePhysical->visibility_details['download_route'];
        $minutes = $this->expireIn($fileVirtual);

        return \URL::temporarySignedRoute(
            $route,
            now()->addMinutes($minutes),
            ['file_virtual' => $fileVirtual->id]
        );
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
