<?php

namespace AnourValar\EloquentFile\Console\Commands;

use Illuminate\Console\Command;

class OnZeroCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eloquent-file:on-zero {--days=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'onZero action on PhysicalFiles without links.';

    /**
     * Execute the console command.
     *
     * @param \AnourValar\EloquentFile\Services\FileService $fileService
     * @throws \RuntimeException
     * @return int
     */
    public function handle(\AnourValar\EloquentFile\Services\FileService $fileService)
    {
        $days = (int) $this->option('days');
        if ($days > 0) {
            $now = now()->subDays($days);
        } else {
            $now = now()->subHours(1);
        }

        $class = config('eloquent_file.models.file_physical');
        $collection = $class
            ::where('counter', '=', 0)
            ->where('updated_at', '<', $now) // index?
            ->select(['id', 'visibility', 'type', 'sha256'])
            ->cursor();

        $counter = 0;
        foreach ($collection as $item) {
            \DB::transaction(function () use ($fileService, $item, $now, &$counter) {
                $fileService->lock($item);
                $item = $item->fresh();

                if ($item && ! $item->counter && $item->updated_at < $now) {
                    $item->getTypeHandler()->onZero($item);
                    $counter++;

                    if ($item->exists && isset($item->counter)) {
                        throw new \RuntimeException('Incorrect onZero behaviour.');
                    }
                }
            });
        }

        if (! $counter) {
            $this->info('Nothing to handle.');
        } else {
            $this->info("$counter file(s) handled.");
        }
        return Command::SUCCESS;
    }
}
