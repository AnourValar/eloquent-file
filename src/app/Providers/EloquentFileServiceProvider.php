<?php

namespace AnourValar\EloquentFile\Providers;

use Illuminate\Support\ServiceProvider;

class EloquentFileServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // config
        $this->mergeConfigFrom(__DIR__.'/../../config/eloquent_file.php', 'eloquent_file');
        $this->publishes([ __DIR__.'/../../config/eloquent_file.php' => config_path('eloquent_file.php')], 'config');

        // factories
        $this->loadFactoriesFrom(__DIR__.'/../../database/factories');

        // migrations
        //$this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->publishes([__DIR__.'/../../database/migrations/' => database_path('migrations')], 'migrations');

        // langs
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang/', 'eloquent-file');
        $this->publishes([__DIR__.'/../../resources/lang/' => resource_path('lang/vendor/eloquent-file')]);

        // models
        $this->publishes([__DIR__.'/../../resources/stubs/' => app_path()], 'models');

        // commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AnourValar\EloquentFile\Console\Commands\RegenerateCommand::class,
            ]);
        }

        // validation rules
        $this->addFileExtRule();
        $this->addFileNotExtRule();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * @return void
     */
    private function addFileExtRule(): void
    {
        \Validator::extend('file_ext', function ($attribute, $value, $parameters, $validator)
        {
            if (! $validator->isValidFileInstance($value)) {
                return false;
            }

            return (
                in_array(mb_strtolower($value->getClientOriginalExtension()), $parameters, true) &&
                in_array(mb_strtolower($value->extension()), $parameters, true)
            );
        });

        \Validator::replacer('file_ext', function ($message, $attribute, $rule, $parameters, $validator)
        {
            return trans(
                'eloquent-file::validation.file_ext',
                ['attribute' => $validator->getDisplayableAttribute($attribute), 'exts' => implode(', ', $parameters)]
            );
        });
    }

    /**
     * @return void
     */
    private function addFileNotExtRule(): void
    {
        \Validator::extend('file_not_ext', function ($attribute, $value, $parameters, $validator)
        {
            if (! $validator->isValidFileInstance($value)) {
                return false;
            }

            return (
                !in_array(mb_strtolower($value->getClientOriginalExtension()), $parameters, true) &&
                !in_array(mb_strtolower($value->extension()), $parameters, true)
            );
        });

        \Validator::replacer('file_not_ext', function ($message, $attribute, $rule, $parameters, $validator)
        {
            return trans(
                'eloquent-file::validation.file_not_ext',
                ['attribute' => $validator->getDisplayableAttribute($attribute), 'exts' => implode(', ', $parameters)]
            );
        });
    }
}
