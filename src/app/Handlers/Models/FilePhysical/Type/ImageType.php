<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

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
                $original = $filePhysical->file_data;
            }

            $encoder = match (mb_strtolower($details['format'])) {
                'jpg' => new \Intervention\Image\Encoders\JpegEncoder(quality: $details['quality']),
                'png' => new \Intervention\Image\Encoders\PngEncoder(),
                'webp' => new \Intervention\Image\Encoders\WebpEncoder(quality: $details['quality']),
                'avif' => new \Intervention\Image\Encoders\AvifEncoder(quality: $details['quality']),
                'heic' => new \Intervention\Image\Encoders\HeicEncoder(quality: $details['quality']),
                default => throw new \LogicException('Format is not supported.'),
            };

            $generate = $this->image
                ->read($original)
                ->scaleDown($details['max_width'], $details['max_height'])
                ->encode($encoder);

            $pathGenerate[$name]['disk'] = $filePhysical->getVisibilityHandler()->getDiskForGenerated($filePhysical, $name);
            $pathGenerate[$name]['path'] = $this->generatePath($filePhysical, sprintf('_%s.%s', $name, $details['format']));

            \Storage::disk($pathGenerate[$name]['disk'])->put($pathGenerate[$name]['path'], $generate);
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
