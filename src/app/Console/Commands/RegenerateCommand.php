<?php

namespace AnourValar\EloquentFile\Console\Commands;

use Illuminate\Console\Command;

class RegenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eloquent-file:regenerate {--created_before=-1 minute}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerates "side files" (GenerateInterface).';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $class = config('eloquent_file.models.file_physical');
        $types = config('eloquent_file.file_physical.type');

        $beforeCreatedAt = \Date::parse($this->option('created_before'));
        $bar = $this->output->createProgressBar(count($types));

        foreach ($types as $type => $typeDetails) {
            $bar->advance();

            $handler = \App::make($typeDetails['bind']);
            if (! $handler instanceof \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\GenerateInterface) {
                continue;
            }

            foreach ($class::where('created_at', '<=', $beforeCreatedAt)->where('type', '=', $type)->cursor() as $item) {
                if (! $item->path) {
                    continue;
                }

                $handler->dispatchGenerate($item);
            }
        }

        $bar->finish();
        return Command::SUCCESS;
    }
}
