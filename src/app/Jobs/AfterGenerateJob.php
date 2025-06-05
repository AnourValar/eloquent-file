<?php

namespace AnourValar\EloquentFile\Jobs;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AfterGenerateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var \AnourValar\EloquentFile\FilePhysical
     */
    private FilePhysical $filePhysical;

    /**
     * @var array
     */
    private array $originalPathGenerate;

    /**
     * @var string
     */
    private string $originalPath;

    /**
     * Create a new job instance.
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param array $originalPathGenerate
     * @param string $originalPath
     * @return void
     */
    public function __construct(FilePhysical $filePhysical, array $originalPathGenerate, string $originalPath)
    {
        $this->filePhysical = $filePhysical;
        $this->originalPathGenerate = $originalPathGenerate;
        $this->originalPath = $originalPath;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        return [];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $new = (array) $this->filePhysical->path_generate;
        foreach ($this->originalPathGenerate as $name => $item) {
            if (isset($new[$name]) && $new[$name] == $item) {
                continue;
            }

            \Storage::disk($item['disk'])->delete($item['path']); // alt: file_deletes [in db] + cron = eventual consistency
        }

        if ($this->filePhysical->path === null) {
            \Storage::disk($this->filePhysical->disk)->delete($this->originalPath); // alt: file_deletes [in db] + cron = eventual consistency
        }
    }
}
