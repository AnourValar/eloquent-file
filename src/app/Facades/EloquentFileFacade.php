<?php

namespace AnourValar\EloquentFile\Facades;

use Illuminate\Support\Facades\Facade;

class EloquentFileFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return \AnourValar\EloquentFile\Services\FileService::class;
    }
}
