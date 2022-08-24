<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

class SimpleType implements TypeInterface
{
    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface::validate()
     */
    public function validate(array $typeDetails, \Illuminate\Validation\Validator $validator): void
    {
        //
    }

    /**
     * {@inheritDoc}
     * @see \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface::onZero()
     */
    public function onZero(FilePhysical $filePhysical): void
    {
        try {
            $filePhysical->validateDelete()->delete();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw \AnourValar\LaravelAtom\Exceptions\InternalValidationException::fromValidationException($e);
        }
    }
}
