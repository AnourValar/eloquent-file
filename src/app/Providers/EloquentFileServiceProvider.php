<?php

namespace AnourValar\EloquentFile\Providers;

use Illuminate\Support\ServiceProvider;

class EloquentFileServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // config
        $this->mergeConfigFrom(__DIR__.'/../../config/eloquent_file.php', 'eloquent_file');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config
        $this->publishes([ __DIR__.'/../../config/eloquent_file.php' => config_path('eloquent_file.php')], 'config');

        // migrations
        //$this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([__DIR__.'/../../database/migrations/' => database_path('migrations')], 'migrations');

        // langs
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang/', 'eloquent-file');
        $this->publishes([__DIR__.'/../../resources/lang/' => lang_path('vendor/eloquent-file')]);

        // models
        $this->publishes([__DIR__.'/../../resources/stubs/' => app_path()], 'models');

        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\EloquentFile\Console\Commands\OnZeroCommand::class,
                \AnourValar\EloquentFile\Console\Commands\RegenerateCommand::class,
            ]);
        }
    }
}
