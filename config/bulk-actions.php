<?php

use Javaabu\BulkActions\Http\Controllers\BulkActionRunsController;
use Javaabu\BulkActions\Http\Controllers\BulkActionsController;
use Javaabu\BulkActions\Models\BulkActionRun;
use Javaabu\BulkActions\Policies\BulkActionRunPolicy;

return [

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Which UI $bulkActions->render() returns: 'blade' renders the Blade view
    | (javaabu/forms v2 components), 'inertia' returns the array the React
    | components (@javaabu/bulk-actions, through javaabu/manifest) take.
    |
    */

    'views' => env('BULK_ACTIONS_VIEWS', 'blade'),

    /*
    |--------------------------------------------------------------------------
    | Chunking and queueing
    |--------------------------------------------------------------------------
    |
    | Records are loaded and handled chunk_size at a time. Selections of more
    | than queue_after records run on the queue (true: always, false: never).
    | Actions and BulkActions classes can override both.
    |
    */

    'chunk_size' => 100,

    'queue_after' => 500,

    'queue' => [
        // null for the default connection and queue
        'connection' => env('BULK_ACTIONS_QUEUE_CONNECTION'),
        'queue' => env('BULK_ACTIONS_QUEUE'),

        // Seconds a queued run may take
        'timeout' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Progress
    |--------------------------------------------------------------------------
    |
    | Milliseconds between status requests while a queued run is shown, and
    | the route name prefix Route::bulkActionRuns() registers.
    |
    */

    'poll_interval' => 2000,

    'runs_route' => 'bulk-actions.runs',

    /*
    |--------------------------------------------------------------------------
    | Select all matching
    |--------------------------------------------------------------------------
    |
    | Without javaabu/filters, "all matching" sends back the listing's query
    | string without these parameters.
    |
    */

    'ignored_parameters' => ['page', 'per_page', 'orderby', 'order'],

    /*
    |--------------------------------------------------------------------------
    | Queued runs
    |--------------------------------------------------------------------------
    |
    | The model and table that track queued runs, and how many days finished
    | runs are kept before `model:prune` deletes them.
    |
    */

    'models' => [
        'run' => BulkActionRun::class,
    ],

    'table' => 'bulk_action_runs',

    'prune_after_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Controllers, policy and permissions
    |--------------------------------------------------------------------------
    |
    | Extend the controllers to change them. The policy decides who may follow
    | and cancel queued runs; it's registered through javaabu/permissions, so
    | config('permissions.policies') and Gate::policy() override it too.
    |
    */

    'controllers' => [
        'bulk_actions' => BulkActionsController::class,
        'runs' => BulkActionRunsController::class,
    ],

    'policy' => BulkActionRunPolicy::class,

    'register_permissions' => true,

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | The Blade view requires the bulk-actions script through
    | javaabu/manifest. Set to false to include your own.
    |
    */

    'assets' => env('BULK_ACTIONS_ASSETS', true),

];
