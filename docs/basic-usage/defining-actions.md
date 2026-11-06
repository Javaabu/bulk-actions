---
title: Defining actions
sidebar_position: 1
---

A `BulkActions` holds the actions of one index page. Define it as a class (the usual way, and required for [queued runs](../advanced-usage/queued-runs.md)), or inline for a one-off.

# A bulk actions class

```php
namespace App\BulkActions;

use App\Models\Article;
use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\Actions\ForceDeleteAction;
use Javaabu\BulkActions\Actions\RestoreAction;
use Javaabu\BulkActions\BulkActions;

class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected function actions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),

            Action::make('publish')
                ->label(__('Publish'))
                ->icon('check')
                ->color('success')
                ->requiresConfirmation(__('Publish the selected articles?'))
                ->successMessage(__(':count articles published.'))
                ->can('publish_articles')
                ->canEach('update')
                ->handle(fn (Collection $articles) => $articles->each->publish()),
        ];
    }
}
```

Create it with `ArticleBulkActions::make()`. Override `setUp()` to configure it further:

```php
protected function setUp(): void
{
    $this->queueAfter(1000)->chunkSize(200);
}
```

# Inline

```php
$bulk_actions = BulkActions::make([
    DeleteAction::make(),
    Action::make('archive')->each(fn (Article $article) => $article->archive()),
])->model(Article::class)->url(route('articles.bulk'));
```

Inline bulk actions run during the request only: a queued run rebuilds the actions in the job, which needs a class. A class configured at runtime (`PostBulkActions::for($post_type)`) keeps what it needs in its [context](../advanced-usage/queued-runs.md#sets-configured-at-runtime-context). An inline action that would be queued throws a `BulkActionsException`; set `->queue(false)` on it, or move it to a class.

# The records

`model()` (or the `$model` property) is the model the actions run on. Every record of it may be selected, unless you narrow the query:

```php
// in a class
public function newQuery(): Builder
{
    return Article::query()->whereBelongsTo(request()->user()->organization);
}

// inline
BulkActions::make([...])->query(fn () => Article::query()->whereBelongsTo($organization));
```

Ids that aren't in the query are ignored, so a tampered request can't reach other records. Use this to scope by tenant or owner, as well as [authorizing](./authorization.md) the action.

`name()` is the name of the row checkboxes, the `model` of javaabu/forms' table (`articles` for `articles[]`). It defaults to the model's plural in snake case (`Article` → `articles`, `BlogPost` → `blog_posts`).

# Actions

```php
Action::make('publish')                    // the name, sent as `action`
    ->label(__('Publish'))                 // default: the name as a headline, through __()
    ->icon('check')                        // a javaabu/forms icon name
    ->color('success')                     // the confirm button's color: primary, danger, warning, success, …
    ->requiresConfirmation()               // ask before running; pass a string for the question
    ->confirmationHeading(__('Publish'))   // default: the label
    ->confirmationText(__('Publish the selected articles?'))
    ->confirmButtonLabel(__('Yes, publish'))
    ->successMessage(__(':count articles published.'))
    ->visible(fn () => request()->routeIs('articles.index'))
    ->meta(['shortcut' => 'p']);           // extra data for custom views and components
```

Every option takes a closure. Closures get arguments by name (`$action`, `$bulkActions`, `$model`, `$user`) or by type.

## What it does

Give each action a handler. `handle()` gets each chunk of records the user may act on; `each()` gets one record at a time.

```php
Action::make('publish')
    ->handle(fn (Collection $articles, array $data, User $user) => $articles->each->publish());

Action::make('archive')
    ->each(fn (Article $article, array $data) => $article->archive($data['reason']));
```

- `$data` is the action form's validated values (see [Actions with forms](./actions-with-forms.md)), or `[]`.
- `handle()` may return how many records succeeded; the rest count as failed. Return nothing when all did.
- With `each()`, a record that throws is counted as failed and the exception is [reported](https://laravel.com/docs/errors#reporting-exceptions); the others carry on. With `handle()`, an exception fails the whole chunk.
- Records are loaded `chunkSize()` at a time (`bulk-actions.chunk_size`, 100), in key order, so a handler that deletes or changes records is safe.

`modifyQueryUsing()` changes the query the records come from, for this action:

```php
Action::make('purge')->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed());
```

## The success message

The message shown afterwards. `:count` is the records that succeeded, `:skipped`, `:failed` and `:total` the others, `:action` the label. Pass a closure to build it from the `$result`:

```php
->successMessage(fn (ActionResult $result) => trans_choice('{1} One article published|[2,*] :count articles published', $result->succeeded))
```

Without one, it's "Publish: 3 records done." When records were skipped or failed, a sentence about them is added.

# Built-in actions

| Action | Name | Does | Checks each record with | Confirms |
| --- | --- | --- | --- | --- |
| `DeleteAction` | `delete` | `$record->delete()` (soft deletes when the model uses `SoftDeletes`) | `delete` | yes |
| `RestoreAction` | `restore` | `$record->restore()`, on trashed records only | `restore` | no |
| `ForceDeleteAction` | `force_delete` | `$record->forceDelete()`, trashed or not | `forceDelete` | yes |

Records are handled one at a time, so model events and observers run. `RestoreAction` and `ForceDeleteAction` are hidden and refused for models without `SoftDeletes`. Everything is configurable as on any action:

```php
DeleteAction::make()->can('delete_articles')->label(__('Move to trash'));
RestoreAction::make()->visible(fn () => request()->routeIs('articles.trash'));
```

To write your own reusable action, extend `Action`, return its name from `getDefaultName()` and configure it in `setUp()`:

```php
class PublishAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'publish';
    }

    protected function setUp(): void
    {
        $this->label(fn () => __('Publish'))
            ->canEach('update')
            ->each(fn (Model $record) => $record->publish());
    }
}
```
