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
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        if ($days > 0) {
            $now = now()->subDays($days);
        } else {
            $now = now()->subHours(1);
        }

        $class = config('eloquent_file.models.file_physical');
        $collection = $class::query()
            ->where('linked', '=', false)
            ->where('updated_at', '<', $now)
            ->select(['id', 'visibility', 'type', 'sha256'])
            ->cursor();

        $counter = 0;
        foreach ($collection as $item) {
            $item->getTypeHandler()->dispatchOnZero($item);
            $counter++;
        }

        if (! $counter) {
            $this->info('No jobs.');
        } else {
            $this->info("$counter job(s) created.");
        }
        return Command::SUCCESS;
    }
}
