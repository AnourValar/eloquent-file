<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

interface TypeInterface
{
    /**
     * Validation
     *
     * @param array $typeDetails
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function validate(array $typeDetails, \Illuminate\Validation\Validator $validator): void;

    /**
     * PhysicalFile has no links (FileVirtuals)
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function onZero(FilePhysical $filePhysical): void;

    /**
     * Send OnZeroJob to a queue
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function dispatchOnZero(FilePhysical $filePhysical): void;
}
