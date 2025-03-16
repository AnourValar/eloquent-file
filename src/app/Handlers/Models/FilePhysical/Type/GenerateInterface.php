<?php

namespace AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type;

use AnourValar\EloquentFile\FilePhysical;

interface GenerateInterface
{
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
     * Send a GenerateJob to the queue
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function dispatchGenerate(FilePhysical $filePhysical): void;
}
