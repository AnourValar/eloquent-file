<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface;

class ImageType extends SimpleType implements GenerateInterface
{
    /**
     * @var \Intervention\Image\ImageManager
     */
    protected \Intervention\Image\ImageManager $image;

    /**
     * DI
     *
     * @return void
     */
    public function __construct()
    {
        $this->image = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Imagick\Driver());
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface::generate()
     */
    public function generate(FilePhysical $filePhysical): array
    {
        $pathGenerate = [];
        $original = null;

        foreach ($filePhysical->type_details['generate'] as $name => $details) {
            if (! $original) {
                $handler = $filePhysical->getVisibilityHandler();
                if ($handler instanceof AdapterInterface) {
                    $original = $handler->getFile($filePhysical->disk, $filePhysical->path);
                } else {
                    $original = \Storage::disk($filePhysical->disk)->get($filePhysical->path);
                }
            }

            $format = match (mb_strtolower($details['format'])) {
                'jpg' => ['encoder' => new \Intervention\Image\Encoders\JpegEncoder(quality: $details['quality']), 'mime' => 'image/jpeg'],
                'png' => ['encoder' => new \Intervention\Image\Encoders\PngEncoder(), 'mime' => 'image/png'],
                'webp' => ['encoder' => new \Intervention\Image\Encoders\WebpEncoder(quality: $details['quality']), 'mime' => 'image/webp'],
                'avif' => ['encoder' => new \Intervention\Image\Encoders\AvifEncoder(quality: $details['quality']), 'mime' => 'image/avif'],
                'heic' => ['encoder' => new \Intervention\Image\Encoders\HeicEncoder(quality: $details['quality']), 'mime' => 'image/heic'],
                default => throw new \LogicException('Format is not supported.'),
            };

            $generate = $this->image
                ->read($original)
                ->scaleDown($details['max_width'], $details['max_height'])
                ->encode($format['encoder']);

            $visibility = config("eloquent_file.file_physical.visibility.{$details['visibility']}");
            $handler = \App::make($visibility['bind']);

            $pathGenerate[$name]['visibility'] = $details['visibility'];
            $pathGenerate[$name]['disk'] = $handler->getDisk($visibility['disks']);
            $pathGenerate[$name]['path'] = $this->generatePath($filePhysical, sprintf('_%s.%s', $name, $details['format']));
            $pathGenerate[$name]['mime_type'] = $format['mime'];

            if ($handler instanceof AdapterInterface) {
                $handler->putFile($pathGenerate[$name]['disk'], $pathGenerate[$name]['path'], $generate);
            } else {
                \Storage::disk($pathGenerate[$name]['disk'])->put($pathGenerate[$name]['path'], $generate);
            }
        }

        return $pathGenerate;
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface::keepOriginal()
     */
    public function keepOriginal(FilePhysical $filePhysical): bool
    {
        return $filePhysical->type_details['keep_original'];
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface::dispatchGenerate()
     */
    public function dispatchGenerate(FilePhysical $filePhysical): void
    {
        \Atom::onCommit(
            function () use ($filePhysical) {
                \AnourValar\EloquentFile\Jobs\GenerateJob::dispatch($filePhysical);
            },
            $filePhysical->getConnectionName()
        );
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param string $suffix
     * @return string
     */
    protected function generatePath(FilePhysical $filePhysical, string $suffix): string
    {
        $pathInfo = pathinfo($filePhysical->path);
        if (isset($pathInfo['extension']) && mb_strlen($pathInfo['extension'])) {
            return mb_substr($filePhysical->path, 0, -(mb_strlen($pathInfo['extension']) + 1)) . $suffix;
        }

        return $filePhysical->path . $suffix;
    }
}
