<?php

namespace Javaabu\BulkActions;

use Illuminate\Support\ServiceProvider;

class BulkActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bulk-actions.php', 'bulk-actions');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'bulk-actions');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/bulk-actions.php' => config_path('bulk-actions.php'),
        ], 'bulk-actions-config');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/bulk-actions'),
        ], 'bulk-actions-translations');
    }
}
