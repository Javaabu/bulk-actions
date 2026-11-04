---
title: Running actions
sidebar_position: 4
---

# Routes

```php
Route::bulkActions('articles', ArticleBulkActions::class);
```

registers `POST articles/bulk`, named `articles.bulk`, which runs the class's actions. `$bulk_actions->getUrl()` finds it (also with cached routes). Options:

| Option | Default | |
| --- | --- | --- |
| `name` | `{uri with dots}.bulk` | The route name |
| `guard` | `null` | Adds `auth:{guard}` instead of `auth` |
| `middleware` | `['auth']` | Replaces the default middleware |
| `controller` | `config('bulk-actions.controllers.bulk_actions')` | A controller with a `store()` method |

```php
Route::bulkActionRuns();
```

registers the endpoints a page polls for [queued runs](../advanced-usage/queued-runs.md): `GET bulk-actions/runs/{run}` and `POST bulk-actions/runs/{run}/cancel`, named `bulk-actions.runs.show` and `.cancel`. Options: `prefix`, `name` (keep it in step with `bulk-actions.runs_route`), `guard`, `middleware`, `controller`.

## In your own controller

Skip the macro and call the bulk actions yourself, for inline bulk actions or extra logic:

```php
public function bulk(Request $request): Response
{
    return ArticleBulkActions::make()->respond($request);
}
```

`execute($request)` runs the action and returns the `ActionResult` without responding.

# The request

Both UIs send the same fields, and so can your own forms or API clients:

| Field | |
| --- | --- |
| `action` | The action's name |
| `{name}[]` | The selected ids, e.g. `articles[]`: the checkboxes of javaabu/forms' table rows |
| `all` | `1` to run on every record matching the listing, instead of ids |
| `filters[...]` | The listing's query string, for `all`. See [Selecting all matching](../advanced-usage/selecting-all-matching.md) |
| `redirect` | Where to go back to; only URLs on this app are followed |
| the action form's fields | See [Actions with forms](./actions-with-forms.md) |

The request is checked in this order:

1. The action exists: otherwise a validation error on `action`.
2. Its `can()`: otherwise **403**.
3. Something is selected: otherwise a validation error on `{name}` ("Select at least one record.").
4. The action's form is valid.

# What happens

The records are the bulk actions' query, changed by the action's `modifyQueryUsing()`, narrowed to the ids (or filtered, for `all`). They're counted, then:

- **Up to `queue_after` records** (500 by default): the action runs during the request, a chunk at a time.
- **More**: a [queued run](../advanced-usage/queued-runs.md) is created and a job runs it.

```php
Action::make('reindex')->queue();          // always queued
Action::make('touch')->queue(false);        // never queued
Action::make('export')->queueAfter(50);     // queued above 50 records

class ArticleBulkActions extends BulkActions
{
    protected function setUp(): void
    {
        $this->queueAfter(1000);            // for every action in the class
    }
}
```

In each chunk, records that fail `canEach()` are skipped, then the handler runs on the rest.

# The response

Requests that want JSON (and aren't Inertia visits) get the result as JSON, `202` when it was queued:

```json
{
    "action": "delete",
    "status": "completed",
    "tone": "warning",
    "total": 3,
    "succeeded": 2,
    "skipped": 1,
    "failed": 0,
    "message": "2 records deleted. 1 record was skipped because you aren't allowed to change it.",
    "run": null,
    "run_url": null
}
```

`status` is `completed`, `queued`, `cancelled` or `failed`. `tone` is `success` when everything succeeded, `warning` when some records were skipped or failed, `danger` when none succeeded, and `info` while queued.

Everything else (forms, Inertia visits) is redirected back to `redirect` with the result flashed. The next page reads it with `$bulk_actions->getResult()`, and the Blade view and `toArray()` (the React prop) include it, so nothing needs sharing.

# Events

| Event | When |
| --- | --- |
| `BulkActionCompleted` | An action finished, during the request or on the queue (`$run` is set then). Has the class, action name, result, selection, form data and user. |
| `BulkActionQueued` | A run was queued. |
| `BulkActionFailed` | A queued run's job failed. |

Listen to `BulkActionCompleted` to log activity or notify someone:

```php
Event::listen(function (BulkActionCompleted $event) {
    activity()->causedBy($event->user)->log("{$event->action}: {$event->result->succeeded} records");
});
```
