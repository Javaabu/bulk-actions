<?php

namespace Javaabu\BulkActions;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Javaabu\BulkActions\Policies\BulkActionRunPolicy;
use Javaabu\BulkActions\Routing\BulkActionRoutes;
use Javaabu\Manifest\Concerns\RegistersAssets;
use Javaabu\Manifest\Package;
use Javaabu\Permissions\Permissions;

class BulkActionsServiceProvider extends ServiceProvider
{
    use RegistersAssets;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bulk-actions.php', 'bulk-actions');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'bulk-actions');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../lang');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'bulk-actions');

        Blade::componentNamespace('Javaabu\\BulkActions\\Views\\Components', 'bulk-actions');

        $this->registerAssets('bulk-actions', __DIR__ . '/../resources', fn (Package $package) => $package
            ->js('js')
            ->dist('../dist')
            ->script('bulk-actions', 'js/blade/bulk-actions.ts', depends_on: ['forms::forms'])
            ->npm([
                '@javaabu/forms' => 'github:Javaabu/react-forms#next-gen',
            ], [
                'react' => '^19.0',
                'react-dom' => '^19.0',
            ]));

        $this->registerRouteMacros();
        $this->registerPermissions();

        if (! $this->app->runningInConsole()) {
            return;
        }

        if (BulkActions::$runs_migrations) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->registerPublishing();
    }

    /**
     * Route::bulkActions('articles', ArticleBulkActions::class) and Route::bulkActionRuns()
     */
    protected function registerRouteMacros(): void
    {
        Router::macro('bulkActions', function (string $uri, string $class, array $options = []) {
            /** @var Router $this */
            return BulkActionRoutes::register($this, $uri, $class, $options);
        });

        Router::macro('bulkActionRuns', function (array $options = []) {
            /** @var Router $this */
            BulkActionRoutes::registerRuns($this, $options);
        });
    }

    /**
     * view_others_bulk_action_runs, cancel_others_bulk_action_runs and the
     * default policy, which the app can override
     */
    protected function registerPermissions(): void
    {
        if (! config('bulk-actions.register_permissions', true)) {
            return;
        }

        Permissions::register('bulk_action_runs', [
            'view_others_bulk_action_runs' => 'View other users\' bulk actions',
            'cancel_others_bulk_action_runs' => 'Cancel other users\' bulk actions',
        ], label: 'Bulk actions');

        Permissions::policy(
            config('bulk-actions.models.run', Models\BulkActionRun::class),
            config('bulk-actions.policy', BulkActionRunPolicy::class),
        );
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bulk-actions.php' => config_path('bulk-actions.php'),
        ], 'bulk-actions-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'bulk-actions-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => lang_path('vendor/bulk-actions'),
        ], 'bulk-actions-translations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/bulk-actions'),
        ], 'bulk-actions-views');
    }
}
