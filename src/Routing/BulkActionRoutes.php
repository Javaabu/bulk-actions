<?php

namespace Javaabu\BulkActions\Routing;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Javaabu\BulkActions\Http\Controllers\BulkActionRunsController;
use Javaabu\BulkActions\Http\Controllers\BulkActionsController;

/**
 * Registers the routes for `Route::bulkActions()` and `Route::bulkActionRuns()`.
 *
 * ```php
 * Route::bulkActions('articles', ArticleBulkActions::class);     // POST articles/bulk, articles.bulk
 * Route::bulkActionRuns();                                       // bulk-actions/runs/{run}, bulk-actions.runs.*
 * ```
 */
class BulkActionRoutes
{
    /** The route action key that holds the BulkActions class a route runs */
    public const string CLASS_ACTION = 'bulk_actions';

    /** @var array<class-string, string|null> */
    protected static array $names = [];

    /**
     * @param  class-string  $class
     * @param  array{
     *     name?: string,
     *     guard?: string|null,
     *     middleware?: string|array<int, string>|null,
     *     controller?: class-string|null
     * }  $options
     */
    public static function register(Router $router, string $uri, string $class, array $options = []): Route
    {
        $uri = trim($uri, '/');
        $name = $options['name'] ?? Str::of($uri)->replace('/', '.')->append('.bulk')->toString();
        $guard = $options['guard'] ?? null;
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controller = $options['controller'] ?? config('bulk-actions.controllers.bulk_actions', BulkActionsController::class);

        static::$names = [];

        return $router->post("{$uri}/bulk", [
            'uses' => "{$controller}@store",
            'middleware' => $middleware,
            self::CLASS_ACTION => $class,
        ])->name($name);
    }

    /**
     * @param  array{
     *     prefix?: string,
     *     name?: string,
     *     guard?: string|null,
     *     middleware?: string|array<int, string>|null,
     *     controller?: class-string|null
     * }  $options
     */
    public static function registerRuns(Router $router, array $options = []): void
    {
        $prefix = trim($options['prefix'] ?? 'bulk-actions/runs', '/');
        $name = rtrim($options['name'] ?? config('bulk-actions.runs_route', 'bulk-actions.runs'), '.');
        $guard = $options['guard'] ?? null;
        $middleware = $options['middleware'] ?? [$guard ? "auth:{$guard}" : 'auth'];
        $controller = $options['controller'] ?? config('bulk-actions.controllers.runs', BulkActionRunsController::class);

        $router->group([
            'prefix' => $prefix,
            'as' => "{$name}.",
            'middleware' => $middleware,
        ], function (Router $router) use ($controller) {
            $router->get('/{bulk_action_run}', [$controller, 'show'])->name('show');
            $router->post('/{bulk_action_run}/cancel', [$controller, 'cancel'])->name('cancel');
        });
    }

    /**
     * The name of the route registered for a BulkActions class. Read from the
     * route collection, so it works with cached routes.
     *
     * @param  class-string  $class
     */
    public static function routeNameFor(string $class): ?string
    {
        if (array_key_exists($class, static::$names)) {
            return static::$names[$class];
        }

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (($route->getAction(self::CLASS_ACTION)) === $class) {
                return static::$names[$class] = $route->getName();
            }
        }

        return static::$names[$class] = null;
    }

    public static function flush(): void
    {
        static::$names = [];
    }
}
