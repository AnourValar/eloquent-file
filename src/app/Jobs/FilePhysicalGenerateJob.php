<?php

namespace AnourValar\EloquentFile\Jobs;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FilePhysicalGenerateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \AnourValar\EloquentFile\FilePhysical
     */
    private $filePhysical;

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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            \DB::connection($this->filePhysical->getConnectionName())->transaction(function ()
            {
                try {
                    $build = $this->filePhysical->getTypeHandler()->getBuild($this->filePhysical->type_details);

                    \App::make(\AnourValar\EloquentFile\Services\FileService::class)->lock($this->filePhysical);
                    $filePhysical = $this->filePhysical->fresh();

                    if (! $filePhysical) {
                        return;
                    }
                    if ($filePhysical->build == $build) {
                        return;
                    }

                    $original = (array)$filePhysical->path_generate;

                    $filePhysical
                        ->fields('build', 'path_generate')
                        ->fill([
                            'build' => $build,
                            'path_generate' => $filePhysical->getTypeHandler()->generate($filePhysical),
                        ])
                        ->validate()
                        ->save();

                    $this->fireEvents($filePhysical);
                    $this->cleanUp($filePhysical, $original);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    \Log::error($e->getMessage(), [$e->validator->errors()->all(), $e->validator->getData()]);

                    throw new \Exception('Internal validation error.');
                }
            });
        } catch (\Throwable $e) {
            $class = config('eloquent_file.models.file_physical');
            $class::where('id', '=', $this->filePhysical->id)->update(['build' => null]);

            throw $e;
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @return void
     */
    private function fireEvents(FilePhysical $filePhysical) : void
    {
        $class = config('eloquent_file.models.file_virtual');

        foreach ($class::where('file_physical_id', '=', $filePhysical->id)->get() as $item) {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($item));
        }
    }

    /**
     * @param \AnourValar\EloquentFile\FilePhysical $filePhysical
     * @param array $original
     * @return void
     */
    private function cleanUp(FilePhysical $filePhysical, array $original) : void
    {
        $items = array_diff($original, (array)$filePhysical->path_generate);

        foreach ($items as $item) {
            if (\Storage::disk($filePhysical->disk)->exists($item)) {
                \Storage::disk($filePhysical->disk)->delete($item);
            }
        }
    }
}
