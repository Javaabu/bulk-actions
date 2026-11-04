---
title: Configuration
sidebar_position: 3
---

Publish the config with `php artisan vendor:publish --tag=bulk-actions-config`.

| Option | Default | |
| --- | --- | --- |
| `views` | `blade` (`BULK_ACTIONS_VIEWS`) | What `$bulkActions->render()` returns: the Blade view, or (`inertia`) the React props |
| `chunk_size` | `100` | Records loaded and handled at a time. Per class or action: `chunkSize()` |
| `queue_after` | `500` | Selections of more records run on the queue; `true` always, `false` never. Per class or action: `queueAfter()`, `queue()` |
| `queue.connection` | `null` (`BULK_ACTIONS_QUEUE_CONNECTION`) | The queue connection for runs |
| `queue.queue` | `null` (`BULK_ACTIONS_QUEUE`) | The queue for runs |
| `queue.timeout` | `3600` | Seconds a run may take |
| `poll_interval` | `2000` | Milliseconds between status requests while a run is shown |
| `runs_route` | `bulk-actions.runs` | The route name prefix of `Route::bulkActionRuns()`, used to build status URLs |
| `ignored_parameters` | `['page', 'per_page', 'orderby', 'order']` | Query parameters left out of "all matching" without javaabu/filters |
| `models.run` | `BulkActionRun::class` | The run model |
| `table` | `bulk_action_runs` | The runs table |
| `prune_after_days` | `7` | When finished runs become prunable |
| `controllers.bulk_actions` | `BulkActionsController::class` | The controller `Route::bulkActions()` uses |
| `controllers.runs` | `BulkActionRunsController::class` | The controller `Route::bulkActionRuns()` uses |
| `policy` | `BulkActionRunPolicy::class` | The runs' policy |
| `register_permissions` | `true` | Register the permissions and the policy with javaabu/permissions |
| `assets` | `true` (`BULK_ACTIONS_ASSETS`) | Require the Blade script through javaabu/manifest |
