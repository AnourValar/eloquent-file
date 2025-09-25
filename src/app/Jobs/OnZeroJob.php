<?php

namespace AnourValar\EloquentFile\Jobs;

use AnourValar\EloquentFile\FilePhysical;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class OnZeroJob implements ShouldQueue, ShouldBeUnique
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
     * @throws \AnourValar\LaravelAtom\Exceptions\InternalValidationException
     * @throws \RuntimeException
     * @return void
     */
    public function handle(\AnourValar\EloquentFile\Services\FileService $fileService)
    {
        \DB::connection($this->filePhysical->getConnectionName())->transaction(function () use ($fileService) {
            $fileService->lock($this->filePhysical);
            $filePhysical = $this->filePhysical->fresh();

            if (! $filePhysical || $filePhysical->linked || $filePhysical->updated_at >= now()->subHours(2)) {
                return;
            }

            $classVirtual = config('eloquent_file.models.file_virtual');
            $classPhysical = config('eloquent_file.models.file_physical');
            if ($classVirtual::where('file_physical_id', '=', $filePhysical->id)->first()) {
                $classPhysical::where('id', '=', $filePhysical->id)->update(['linked' => true, 'updated_at' => now()]);
                return;
            }

            try {
                $filePhysical->getTypeHandler()->onZero($filePhysical);

                if ($filePhysical->exists) {
                    throw new \RuntimeException('Incorrect onZero behaviour.');
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->fail(); // no retry
                throw \AnourValar\LaravelAtom\Exceptions\InternalValidationException::fromValidationException($e);
            }
        });
    }
}
