<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

class ImageType extends SimpleType implements GenerateInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface::validate()
     */
    public function validate(array $typeDetails, \Illuminate\Validation\Validator $validator): void
    {
        $file = $validator->getData()['file'];

        // Mime
        if (mb_substr($file->getMimeType(), 0, 5) != 'image') {
            $validator->errors()->add('file', trans('eloquent-file::file_physical.type_handlers.image.incorrect'));
            return;
        }

        // Exif
        $exif = var_export(\Image::make($file)->exif(), true);
        if (stripos($exif, '<?') !== false || stripos($exif, '<%') !== false) {
            $validator->errors()->add('file', trans('eloquent-file::file_physical.type_handlers.image.incorrect'));
            return;
        }
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface::getBuild()
     */
    public function getBuild(array $typeDetails): int
    {
        return $typeDetails['build'];
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
                $original = \Storage::disk($filePhysical->disk)->get($filePhysical->path);
            }

            $generate = \Image
                ::make($original)
                ->orientate()
                ->resize($details['max_width'], $details['max_height'], function ($constraint)
                {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->encode($details['format'], $details['quality']);

            if ($details['alt_disks']) {
                $disks = $details['alt_disks'];
                shuffle($disks);
                $pathGenerate[$name]['disk'] = $disks[0];
            } else {
                $pathGenerate[$name]['disk'] = $filePhysical->disk;
            }
            $pathGenerate[$name]['path'] = $this->generatePath($filePhysical, sprintf("_%s.%s", $name, $details['format']));

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
