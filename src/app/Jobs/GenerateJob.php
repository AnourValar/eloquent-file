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
     */
    public function handle(\AnourValar\EloquentFile\Services\FileService $fileService)
    {
        try {
            $filePhysical = \DB::connection($this->filePhysical->getConnectionName())->transaction(function () use ($fileService) {
                $fileService->lock($this->filePhysical);
                $filePhysical = $this->filePhysical->fresh();

                if (! $filePhysical) {
                    return false;
                }

                $build = $filePhysical->getTypeHandler()->getBuild($filePhysical->type_details);
                if ($filePhysical->build == $build) {
                    return false;
                }
                if (! $filePhysical->path) {
                    return false;
                }

                $filePhysical->forceFill(['build' => $build])->save();
                return $filePhysical;
            });

            if (! $filePhysical) {
                return;
            }

            try {
                $original = (array) $filePhysical->path_generate;

                $filePhysical
                    ->forceFill(['path_generate' => $filePhysical->getTypeHandler()->generate($filePhysical)])
                    ->validate()
                    ->save();

                $this->cleanUp($filePhysical, $original)->fireEvents($filePhysical);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->fail(); // no retry
                throw \AnourValar\LaravelAtom\Exceptions\InternalValidationException::fromValidationException($e);
            }
        } catch (\Throwable $e) {
            $class = config('eloquent_file.models.file_physical');
            $class::where('id', '=', $this->filePhysical->id)->update(['build' => null]);

            throw $e;
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param array $original
     * @return self
     */
    private function cleanUp(FilePhysical &$filePhysical, array $original): self
    {
        $new = (array) $filePhysical->path_generate;
        foreach ($original as $name => $item) {
            if (isset($new[$name]) && $new[$name] == $item) {
                continue;
            }

            if (\Storage::disk($item['disk'])->exists($item['path'])) {
                \Storage::disk($item['disk'])->delete($item['path']);
            }
        }

        if (! $filePhysical->getTypeHandler()->keepOriginal($filePhysical)) {
            if (\Storage::disk($filePhysical->disk)->exists($filePhysical->path)) {
                \Storage::disk($filePhysical->disk)->delete($filePhysical->path);
            }

            $filePhysical->path = null;
            $filePhysical->save();
        }

        return $this;
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return self
     */
    private function fireEvents(FilePhysical $filePhysical): self
    {
        $class = config('eloquent_file.models.file_virtual');

        foreach ($class::where('file_physical_id', '=', $filePhysical->id)->get() as $item) {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($item));
        }

        return $this;
    }
}
