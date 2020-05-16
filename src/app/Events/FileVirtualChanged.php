<?php

namespace AnourValar\EloquentFile\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileVirtualChanged
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

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
