<?php

namespace AnourValar\EloquentFile\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileVirtualChanged // listeners should not be queued
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var \AnourValar\EloquentFile\FileVirtual
     */
    public $fileVirtual;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(\AnourValar\EloquentFile\FileVirtual $fileVirtual)
    {
        $this->fileVirtual = $fileVirtual;
    }
}
