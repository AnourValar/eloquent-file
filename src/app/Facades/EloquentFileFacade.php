<?php

namespace AnourValar\EloquentFile\Facades;

use Illuminate\Support\Facades\Facade;

class EloquentFileFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \AnourValar\EloquentFile\Services\FileService::class;
    }
}
