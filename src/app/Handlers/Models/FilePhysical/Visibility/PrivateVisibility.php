<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\FileVirtual;
use Illuminate\Http\UploadedFile;

class PrivateVisibility implements VisibilityInterface, ProxyInterface
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
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyInterface::generateUrl()
     */
    public function generateUrl(FileVirtual $fileVirtual, bool $guest = false): string
    {
        $route = $fileVirtual->filePhysical->visibility_details['download_route'];
        $minutes = $this->expireIn($fileVirtual);

        return \URL::temporarySignedRoute(
            $route,
            now()->addMinutes($minutes),
            ['file_virtual' => $fileVirtual->id, 'guest' => (int)$guest]
        );
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyInterface::download()
     */
    public function download(FileVirtual $fileVirtual): \Symfony\Component\HttpFoundation\Response
    {
        $pathInfo = pathinfo($fileVirtual->filename);
        if (isset($pathInfo['extension'])) {
            $filename = \Str::slug($pathInfo['filename']) . '.' . \Str::slug($pathInfo['extension']);
        } else {
            $filename = \Str::slug($pathInfo['filename']);
        }

        return \Storage
            ::disk($fileVirtual->filePhysical->disk)
            ->download($fileVirtual->filePhysical->path, $filename, ['Content-Type' => $fileVirtual->content_type]);
    }

    /**
     * Returns the number of minutes during which the url will be actual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return integer
     */
    protected function expireIn(FileVirtual $fileVirtual): int
    {
        return ceil($fileVirtual->filePhysical->size / (1024 * 1024)) + 10;
    }
}
