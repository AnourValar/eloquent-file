<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;

$factory->define(config('eloquent_file.models.file_virtual'), function (Faker $faker, $attributes)
{
    if (empty($attributes['entity']) || empty($attributes['entity_id'])) {
        throw new \LogicException('Attributes "entity", "entity_id" are required.');
    }

    $class = config('eloquent_file.models.file_physical');
    if (isset($attributes['file_physical_id'])) {
        $filePhysical = $class::find($attributes['file_physical_id']);
    } else {
        $filePhysical = $class::orderBy('id', 'DESC')->first();
    }

    if (! $filePhysical) {
        throw new \LogicException('File physical must be created firstly.');
    }

    return [
        'file_physical_id' => $filePhysical->id,
        'filename' => basename($filePhysical->path),
        'content_type' => $filePhysical->mime_type,
    ];
});
