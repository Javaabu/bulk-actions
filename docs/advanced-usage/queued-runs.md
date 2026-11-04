---
title: Queued runs
sidebar_position: 2
---

A selection of more than `queue_after` records (500 by default; see [Running actions](../basic-usage/running-actions.md#what-happens)) runs on the queue. The request creates a `BulkActionRun`, dispatches a `RunBulkAction` job, and redirects back with a "running in the background" result. The page polls the run's status and shows its progress.

Register the status endpoints:

```php
Route::bulkActionRuns();
```

# The job

`RunBulkAction` rebuilds the bulk actions class (`ArticleBulkActions::make()`) for the user who started the run, so:

- the bulk actions must be a class (inline ones can't be queued);
- actions are authorized for that user (`canEach()` per record);
- it runs in the locale the run was started in, so messages are in the user's language.

It counts the records again, then handles them a chunk at a time, saving the counts after each chunk. It has one try and a timeout of `bulk-actions.queue.timeout` (3600 seconds). Set the connection and queue with `bulk-actions.queue.connection` and `.queue`.

# BulkActionRun

| Attribute | |
| --- | --- |
| `uuid` | The route key |
| `user` | The user who started it (morph) |
| `bulk_actions`, `name`, `action` | The class, the selection's name and the action |
| `selection` | `ids`, `all` and `filters` |
| `data` | The action form's values |
| `status` | `RunStatuses`: `pending`, `processing`, `completed`, `failed`, `cancelled` |
| `total`, `succeeded`, `skipped`, `failed` | Counts |
| `message`, `error` | The result message, and the exception message of a failed job |
| `locale`, `started_at`, `finished_at` | |

Methods: `progress()` (0–100), `isFinished()`, `isCancellable()`, `cancel()`, `isOwnedBy($user)`, `statusUrl()`, `cancelUrl()`, `toResult()`, `toStatusArray()`, and the `ownedBy($user)` scope.

Use your own model with `bulk-actions.models.run` (extend `BulkActionRun`), and another table with `bulk-actions.table`.

# Status and cancelling

`GET bulk-actions/runs/{uuid}` returns:

```json
{
    "data": {
        "id": "9d3c…",
        "name": "articles",
        "action": "delete",
        "status": "processing",
        "status_label": "Running",
        "progress": 25,
        "total": 2000,
        "processed": 500,
        "succeeded": 498,
        "skipped": 2,
        "failed": 0,
        "message": "Delete will run on 2000 records in the background.",
        "is_finished": false,
        "is_cancellable": true,
        "cancel_url": "https://example.test/bulk-actions/runs/9d3c…/cancel",
        "result": { "action": "delete", "status": "queued", "tone": "info", "…": "…" }
    }
}
```

The UIs poll it every `bulk-actions.poll_interval` milliseconds (2000) until `is_finished`. `POST …/cancel` stops the run after the chunk it's on; records already handled stay handled.

# Who may see and cancel runs

`BulkActionRunPolicy`, registered through javaabu/permissions:

| Ability | Who |
| --- | --- |
| `view` | The user who started the run, or users with `view_others_bulk_action_runs` |
| `cancel` | The user who started the run, or users with `cancel_others_bulk_action_runs` |

Run `php artisan permissions:sync` to create the permissions. Override the policy with `bulk-actions.policy`, `config('permissions.policies')` or `Gate::policy()`. Set `bulk-actions.register_permissions` to `false` to register neither.

# Pruning

Finished runs older than `bulk-actions.prune_after_days` (7) are prunable:

```php
Schedule::command('model:prune', ['--model' => [BulkActionRun::class]])->daily();
```

# Testing

With the `sync` queue driver, the job runs during the request and the response already has the finished result. To test the queuing itself:

```php
Queue::fake();

$this->actingAs($admin)
    ->postJson(route('articles.bulk'), ['action' => 'publish', 'all' => 1])
    ->assertStatus(202)
    ->assertJson(['status' => 'queued']);

Queue::assertPushed(RunBulkAction::class);
```
