---
title: API reference
sidebar_position: 7
---

# BulkActions

`Javaabu\BulkActions\BulkActions`

| Method | |
| --- | --- |
| `static make(array $actions = [])` | A new instance; calls `setUp()` |
| `protected setUp()` | Configure a class |
| `protected actions(): array` | The actions of a class |
| `withActions(array $actions)` | Add actions |
| `model(?string $model)` / `getModel()` | The model; or the `$model` property |
| `name(?string $name)` / `getName()` | The selection's name (`articles[]`) |
| `url(string\|Closure\|null $url)` / `getUrl()` | Where the form posts; default: the `Route::bulkActions()` route |
| `query(Builder\|Closure\|null $query)` / `newQuery()` | The records that may be selected; override `newQuery()` in a class |
| `filters(mixed $filters)` / `getFilters()` | How "all matching" filters: a javaabu/filters class or instance, or a closure |
| `applyFilters(Builder $query, array $parameters)` | Apply them |
| `filterParameters(?array $parameters)` / `getFilterParameters()` | What "all matching" sends back |
| `chunkSize(?int $size)` / `getChunkSize()` | Default chunk size for the actions |
| `queueAfter(bool\|int\|null $count)` / `getQueueAfter()` | Default queueing for the actions |
| `user(?Authorizable $user)` / `getUser()` | Who the actions are authorized for; default: the request's user |
| `total(?int $total)` / `getTotal()` | The listing's total, for "select all matching" |
| `paginator(Paginator $paginator)` / `getPageCount()` | Total and page size from a paginator |
| `getActions()` / `getAction(?string $name)` / `hasAction(string $name)` | Every action |
| `getAvailableActions()` / `hasAvailableActions()` | Visible actions whose `can()` passes |
| `isInline()` | Not a subclass; can't be queued |
| `execute(?Request $request): ActionResult` | Validate and run (or queue) |
| `respond(?Request $request): Response` | Execute, then JSON or a redirect with the result flashed |
| `getResult(): ?ActionResult` / `sessionKey()` | The flashed result |
| `getStrings()` | The UI strings, translated |
| `toArray()` / `jsonSerialize()` | The React props (below) |
| `render(?string $views = null)` / `toHtml()` / `__toString()` | The Blade view, or the props for `inertia` |
| `static $runs_migrations` | `false` to stop loading the migration |

# Action

`Javaabu\BulkActions\Actions\Action`; `DeleteAction`, `RestoreAction` and `ForceDeleteAction` extend it.

| Method | |
| --- | --- |
| `static make(?string $name = null)` | A new action; built-ins have a default name (`getDefaultName()`) |
| `getName()` | |
| `label()` / `getLabel()` | Default: the name as a headline, through `__()` |
| `icon()` / `getIcon()` | |
| `color()` / `getColor()` | Default `primary` |
| `requiresConfirmation(bool\|string\|Closure $condition = true)`, alias `confirm()` / `requiresConfirmationBeforeRunning()` | |
| `confirmationHeading()` / `getConfirmationHeading()` | Default: the label |
| `confirmationText()` / `getConfirmationText()` | |
| `confirmButtonLabel()` / `getConfirmButtonLabel()` | Default: the label |
| `successMessage(string\|Closure\|null)` / `getSuccessMessage(ActionResult $result)` | `:count`, `:skipped`, `:failed`, `:total`, `:action` |
| `meta(array)` / `getMeta()` | |
| `visible()` / `hidden()` / `isVisible()` | Presentation only |
| `can(string\|Closure\|null $ability, mixed $arguments = [])` / `getCan()` / `getCanArguments()` / `isAuthorized(?Authorizable $user)` | Who may run it |
| `canEach(string\|Closure\|null $ability)` / `getCanEach()` / `isAuthorizedFor(?Authorizable $user, Model $record)` | Which records |
| `isAvailableFor(?Authorizable $user)` | Visible and authorized |
| `form(array\|Closure\|Form\|null)` / `hasForm()` / `getForm(): ?Form` / `getFormId()` | The stencil form |
| `handle(Closure)` / `each(Closure)` / `hasHandler()` / `processesEachRecord()` | What it does |
| `process(Collection $records, array $data, ?Authorizable $user): int` | Run the handler on records |
| `modifyQueryUsing(?Closure)` / `modifyQuery(Builder $query)` | |
| `chunkSize(?int)` / `getChunkSize()` | |
| `queue(bool\|int\|null $condition = true)` / `queueAfter(?int)` / `shouldQueue(int $count)` | |
| `toArray()` | The action's props |

Closures get `$action`, `$bulkActions`, `$model`, `$user` by name, and `$records`, `$record`, `$data`, `$query`, `$result` where they apply, or arguments by type.

# Results and runs

- `Execution\ActionResult`: `action`, `status` (`COMPLETED`, `QUEUED`, `CANCELLED`, `FAILED`), `total`, `succeeded`, `skipped`, `failed`, `message`, `run`, `run_url`; `processed()`, `isQueued()`, `tone()`, `toArray()`, `fromArray()`.
- `Execution\Selection`: `ids`, `all`, `filters`; `fromRequest(Request, string $name)`, `isEmpty()`.
- `Execution\Runner`: runs an action on a selection in chunks; used by the request and the job.
- `Execution\Executor`: validates, authorizes, runs or queues, and responds.
- `Models\BulkActionRun`, `Enums\RunStatuses`, `Jobs\RunBulkAction`: see [Queued runs](./queued-runs.md).
- Events: `BulkActionCompleted`, `BulkActionQueued`, `BulkActionFailed`.
- `Policies\BulkActionRunPolicy`, `Http\Controllers\BulkActionsController`, `Http\Controllers\BulkActionRunsController`, `Routing\BulkActionRoutes`.
- `Support\FiltersAdapter` (javaabu/filters without a hard dependency), `Support\QueryFields` (nested parameters as hidden fields).
- `Views\Components\Actions`: `<x-bulk-actions::actions>`.
- `Exceptions\BulkActionsException`: a missing name, handler, model or URL, duplicate names, queueing inline bulk actions.

# Route macros

- `Route::bulkActions(string $uri, string $class, array $options = [])`: `name`, `guard`, `middleware`, `controller`.
- `Route::bulkActionRuns(array $options = [])`: `prefix`, `name`, `guard`, `middleware`, `controller`.

# The React props

`$bulkActions->toArray()`, typed as `BulkActionsSchema` in `@javaabu/bulk-actions`:

```jsonc
{
  "name": "articles",                       // the row checkboxes: articles[]
  "url": "https://example.test/articles/bulk",
  "actions": [
    {
      "name": "change_status",
      "label": "ހާލަތު ބަދަލުކުރޭ",
      "icon": null,
      "color": "primary",
      "confirmation": { "heading": "ހާލަތު ބަދަލުކުރޭ", "text": null, "button": "ހާލަތު ބަދަލުކުރޭ" },  // null without a confirmation or form
      "form": { "stencil": 1, "embedded": true, "components": [ /* stencil nodes */ ] },              // null without a form
      "meta": null
    }
  ],
  "total": 1234,                            // null hides "select all matching"
  "pageCount": 20,
  "filters": { "search": "މާލެ" },          // sent back as filters[...] with all=1
  "result": null,                           // ActionResult::toArray() after an action
  "old": { "action": null, "all": false, "ids": [] },
  "pollInterval": 2000,
  "strings": { "apply": "Apply", "selected": ":count selected", "…": "…" }
}
```

# JavaScript

`@javaabu/bulk-actions` (React): `BulkActionsTable`, `BulkActionsBar`, `BulkActionForm`, `BulkActionsResult`, `useBulkActions`, `useCheckedCount`, `useRunStatus`, `fetchRun`, `cancelRun`, `pollRun`, `flattenQuery`, `replace`, and the types. See [Inertia React](../basic-usage/react.md).

`@javaabu/bulk-actions/blade/bulk-actions` (the Blade script): `init(root)`, `initBar(element)`, `initProgress(alert, config)`. See [Blade](../basic-usage/blade.md#the-script).
