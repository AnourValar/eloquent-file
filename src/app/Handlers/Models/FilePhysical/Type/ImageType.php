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
        $preview = \Image::make(\Storage::disk($filePhysical->disk)->get($filePhysical->path));
        $typeDetails = $filePhysical->type_details;

        $preview = $preview
            ->orientate()
            ->resize($typeDetails['preview']['max_width'], $typeDetails['preview']['max_height'], function ($constraint)
            {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode($typeDetails['preview']['format'], $typeDetails['preview']['quality']);

        $pathGenerate = [];
        if ($typeDetails['preview']['alt_disks']) {
            $disks = $typeDetails['preview']['alt_disks'];
            shuffle($disks);
            $pathGenerate['preview']['disk'] = $disks[0];
        } else {
            $pathGenerate['preview']['disk'] = $filePhysical->disk;
        }
        $pathGenerate['preview']['path'] = $this->generatePath($filePhysical, "_preview.{$typeDetails['preview']['format']}");

        \Storage::disk($pathGenerate['preview']['disk'])->put($pathGenerate['preview']['path'], $preview);

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
