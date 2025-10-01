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
    public const METHOD_URL_SIGNED = 'url_signed';
    public const METHOD_URL_SIGNED_DIRECT = 'url_signed_direct'; // working offline
    public const METHOD_USER_AUTHORIZE = 'user_authorize';

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
    public function getDisk(array $disks): string
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
        if (! isset($filePhysical->sha256, $filePhysical->id)) {
            throw new \LogicException('Incorrect usage.');
        }

        $extension = $file->getClientOriginalExtension();
        //if (! mb_strlen($extension)) {
        //     $extension = $file->extension();
        //}
        if (mb_strlen($extension)) {
            $extension = ".$extension";
        }

        return mb_substr($filePhysical->sha256, 0, 2).'/'
            .mb_substr($filePhysical->sha256, 2, 2).'/'
            .mb_substr($filePhysical->sha256, 4, 2).'/'
            .$filePhysical->sha256
            .$filePhysical->id // important!
            .$extension;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface::proxyUrl()
     */
    public function proxyUrl(FileVirtual $fileVirtual, ?string $generate = null): string
    {
        if ($generate) {
            $visibility = $fileVirtual->filePhysical->path_generate[$generate]['visibility'];
            $route = config("eloquent_file.file_physical.visibility.{$visibility}.proxy_route");
            $method = config("eloquent_file.file_physical.visibility.{$visibility}.proxy_route_method");

            $disk = $fileVirtual->filePhysical->path_generate[$generate]['disk'];
            $path = $fileVirtual->filePhysical->path_generate[$generate]['path'];
            $filename = $this->getFileName($fileVirtual, $generate);
        } else {
            $route = ($fileVirtual->filePhysical->visibility_details['proxy_route'] ?? null);
            $method = $fileVirtual->filePhysical->visibility_details['proxy_route_method'];

            $disk = $fileVirtual->filePhysical->disk;
            $path = $fileVirtual->filePhysical->path;
            $filename = $this->getFileName($fileVirtual);
        }

        if ($method === static::METHOD_URL_SIGNED) {
            return \URL::temporarySignedRoute(
                $route,
                now()->addMinutes($this->expireIn($fileVirtual)),
                ['file_virtual' => $fileVirtual->id, 'filename' => $filename, 'generate' => $generate]
            );
        }

        if ($method === static::METHOD_URL_SIGNED_DIRECT) {
            if (! \Storage::disk($disk)->providesTemporaryUrls()) {
                throw new \LogicException('Disk driver does not support temporary urls.');
            }

            return url(
                \Storage::disk($disk)
                    ->temporaryUrl(
                        $path,
                        now()->addMinutes($this->expireIn($fileVirtual)),
                        ['ResponseContentDisposition' => 'inline; filename="'.$filename.'"']
                    )
            );
        }

        if ($method === static::METHOD_USER_AUTHORIZE) {
            return route($route, ['file_virtual' => $fileVirtual->id, 'filename' => $filename, 'generate' => $generate]);
        }

        throw new \LogicException('Option "proxy_route_method" must be set properly.');
    }

    /**
     * Returns the number of minutes during which the url will be actual
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @return int
     */
    protected function expireIn(FileVirtual $fileVirtual): int
    {
        return (int) (ceil($fileVirtual->filePhysical->size / (1024 * 1024)) + 10);
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string|null $generate
     * @return string
     */
    protected function getFileName(FileVirtual $fileVirtual, ?string $generate = null): string
    {
        $fileName = pathinfo($fileVirtual->filename)['filename'];
        if ($generate) {
            $extension = pathinfo($fileVirtual->filePhysical->path_generate[$generate]['path'])['extension'] ?? '';
            $suffix = '_' . $generate;
        } else {
            $extension = pathinfo($fileVirtual->filename)['extension'] ?? '';
            $suffix = '';
        }

        if (mb_strlen($extension)) {
            return \Str::slug($fileName) . $suffix . '.' . \Str::slug($extension);
        }

        return \Str::slug($fileName) . $suffix;
    }
}
