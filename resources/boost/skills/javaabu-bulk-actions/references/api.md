# javaabu/bulk-actions API

## BulkActions

| Method | |
| --- | --- |
| `static make(array $actions = [])` | Instance; calls `setUp()` |
| `protected actions(): array`, `protected setUp()` | In a class |
| `model(class-string)` / `$model` | The model |
| `name(string)` | Selection name; default plural snake of the model (`articles`) |
| `url(string\|Closure)` / `getUrl()` | Default: the `Route::bulkActions()` route |
| `query(Builder\|Closure)` / override `newQuery()` | Records that may be selected |
| `filters(class-string\|object\|Closure)` | "All matching": javaabu/filters or `fn (Builder $query, array $filters) => …` |
| `filterParameters(array)` | What "all matching" sends back; default from the filters or the query string |
| `chunkSize(int)`, `queueAfter(bool\|int)` | Defaults for the actions |
| `user(Authorizable)` | Default: the request's user |
| `paginator(Paginator)`, `total(int)` | For "select all matching" |
| `getActions()`, `getAction($name)`, `getAvailableActions()` | |
| `execute(?Request): ActionResult`, `respond(?Request): Response` | Run from your own controller |
| `getResult()` | The flashed result |
| `toArray()` | React props; `render()` Blade or props by `bulk-actions.views` |

## Action

| Method | |
| --- | --- |
| `make('name')` | Built-ins: `DeleteAction::make()` (`delete`), `RestoreAction::make()` (`restore`), `ForceDeleteAction::make()` (`force_delete`) |
| `label()`, `icon()`, `color()`, `meta()` | |
| `requiresConfirmation(bool\|string)` / `confirm()`, `confirmationHeading()`, `confirmationText()`, `confirmButtonLabel()` | |
| `successMessage(string\|Closure)` | `:count`, `:skipped`, `:failed`, `:total`, `:action`; closure gets `$result` |
| `can($ability, $arguments = [])` | Gate check without records; closure gets `$user` |
| `canEach($ability)` | Gate check per record; closure gets `$user`, `$record` |
| `visible()`, `hidden()` | Presentation only |
| `form(array\|Closure\|Form)` | Stencil fields; validated values are `$data` |
| `handle(Closure)` | Per chunk: `$records`, `$data`, `$user`; may return the count that succeeded |
| `each(Closure)` | Per record: `$record` (or by type), `$data`, `$user`; throwing records count as failed |
| `modifyQueryUsing(fn (Builder $query) => …)` | e.g. `onlyTrashed()` |
| `chunkSize(int)`, `queue(bool\|int)`, `queueAfter(int)` | |

To write a reusable action: extend `Action`, return the name from `static getDefaultName()`, configure in `setUp()`.

## The request

`action`, `{name}[]` (ids) or `all=1` with `filters[...]`, `redirect`, and the form's fields. Checked in order: action exists (422 `action`), `can()` (403), selection (422 `{name}`), form (422 per field).

JSON requests get `ActionResult::toArray()`: `action`, `status` (`completed|queued|cancelled|failed`), `tone` (`success|warning|danger|info`), `total`, `succeeded`, `skipped`, `failed`, `message`, `run`, `run_url`; `202` when queued. Others are redirected back with it flashed in `bulk-actions.results.{name}`.

## Queued runs

- Above `bulk-actions.queue_after` (500). `BulkActionRun` (uuid, user morph, selection, data, status, counts, message), `RunBulkAction` job (rebuilds the class for the run's user, in the run's locale, chunk by chunk, stops when cancelled).
- `Route::bulkActionRuns()`: `GET bulk-actions/runs/{uuid}` (`data`: status, progress, counts, `is_finished`, `cancel_url`), `POST …/cancel`.
- Policy: owner, or `view_others_bulk_action_runs` / `cancel_others_bulk_action_runs` (`php artisan permissions:sync`). Override via `config('permissions.policies')`.
- Events: `BulkActionCompleted` (also for runs during the request), `BulkActionQueued`, `BulkActionFailed`.
- Prune: `Schedule::command('model:prune', ['--model' => [BulkActionRun::class]])->daily()`.

## React (`@javaabu/bulk-actions`)

`BulkActionsTable` (`bulkActions`, `components`, `onRunFinished`, plus `Table` props), or the parts: `useBulkActions(schema)` → `state.apply` for `bulkFormProps.onSubmit`, `BulkActionsBar` (in `bulkForm`), `BulkActionForm` (outside the table), `BulkActionsResult`, `useRunStatus`. Types: `BulkActionsSchema`, `BulkAction`, `BulkActionResult`, `BulkActionRunStatus`.

## Config (`config/bulk-actions.php`)

`views`, `chunk_size`, `queue_after`, `queue.connection|queue|timeout`, `poll_interval`, `runs_route`, `ignored_parameters`, `models.run`, `table`, `prune_after_days`, `controllers.bulk_actions|runs`, `policy`, `register_permissions`, `assets`.
