<?php

namespace SaadBhutto\DatabaseDump;

use App\Console\Commands\DatabaseDumpCommand;
use Illuminate\Support\ServiceProvider;

class DatabaseDumpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('dumpster.php'),
            ], 'config');

            // Registering package commands.
            $this->commands([
                DatabaseDumpCommand::class
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'dumpster');

        // Register the main class to use with the facade
        $this->app->singleton('database-dump', function () {
            return new DatabaseDump;
        });
    }
}
