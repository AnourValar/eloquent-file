<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

interface GenerateInterface
{
    /**
     * Returns the build (version) of the generator
     *
     * @param array $typeDetails
     * @return int
     */
    public function getBuild(array $typeDetails): int;

    /**
     * Generates side files and returns an array of paths (path_generate)
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return array|null
     */
    public function generate(FilePhysical $filePhysical): ?array;

    /**
     * Keep original file
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return bool
     */
    public function keepOriginal(FilePhysical $filePhysical): bool;

    /**
     * Send GenerateJob to a queue
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function dispatchGenerate(FilePhysical $filePhysical): void;
}
