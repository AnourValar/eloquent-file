<?php

namespace AnourValar\EloquentFile\Jobs;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @var \AnourValar\EloquentFile\FilePhysical
     */
    private FilePhysical $filePhysical;

    /**
     * Create a new job instance.
     *
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    public function __construct(FilePhysical $filePhysical)
    {
        $this->filePhysical = $filePhysical;
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
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return (string) $this->filePhysical->id;
    }

    /**
     * Execute the job.
     *
     * @param \AnourValar\EloquentFile\Services\FileService $fileService
     * @return void
     * @psalm-suppress UnusedVariable
     */
    public function handle(\AnourValar\EloquentFile\Services\FileService $fileService)
    {
        \DB::connection($this->filePhysical->getConnectionName())->transaction(function () use ($fileService) {
            $fileService->lock($this->filePhysical);
            $filePhysical = $this->filePhysical->fresh();

            if (! $filePhysical) {
                return;
            }

            $originalPathGenerate = (array) $filePhysical->path_generate;
            $originalPath = $filePhysical->path;

            $filePhysical->path_generate = $filePhysical->getTypeHandler()->generate($filePhysical);
            if (! $filePhysical->getTypeHandler()->keepOriginal($filePhysical)) {
                $filePhysical->path = null;
            }

            $filePhysical->save();
            $this->fireEvents($filePhysical);

            \Atom::onCommit(
                fn () => $this->cleanUp($filePhysical, $originalPathGenerate, $originalPath),
                $filePhysical->getConnectionName()
            );
        });
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    private function fireEvents(FilePhysical &$filePhysical): void
    {
        $class = config('eloquent_file.models.file_virtual');

        foreach ($class::where('file_physical_id', '=', $filePhysical->id)->cursor() as $item) {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($item));
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param array $originalPathGenerate
     * @param string $originalPath
     * @return self
     * @psalm-suppress UnusedMethod
     */
    private function cleanUp(FilePhysical $filePhysical, array $originalPathGenerate, string $originalPath): self
    {
        $new = (array) $filePhysical->path_generate;
        foreach ($originalPathGenerate as $name => $item) {
            if (isset($new[$name]) && $new[$name] == $item) {
                continue;
            }

            \Storage::disk($item['disk'])->delete($item['path']); // alt: file_deletes [in db] + cron = eventual consistency
        }

        if ($filePhysical->path === null) {
            \Storage::disk($filePhysical->disk)->delete($originalPath); // alt: file_deletes [in db] + cron = eventual consistency
        }

        return $this;
    }
}
