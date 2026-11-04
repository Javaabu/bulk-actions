---
name: javaabu-bulk-actions
description: Add bulk actions to Laravel index pages with javaabu/bulk-actions. Use when defining a BulkActions class (Javaabu\BulkActions\BulkActions with $model and actions()), actions (Action::make()->handle()/each(), DeleteAction, RestoreAction, ForceDeleteAction), authorizing them with can() and canEach(), giving an action a stencil form, registering Route::bulkActions() and Route::bulkActionRuns(), rendering the UI in Blade (<x-bulk-actions::actions> in <x-forms::table>'s bulk-form slot) or Inertia React (BulkActionsTable from @javaabu/bulk-actions), selecting all records matching javaabu/filters, queued runs and progress, or testing bulk actions.
license: MIT
metadata:
  author: Javaabu
---

# Bulk actions with javaabu/bulk-actions

The docs ship with the package: `vendor/javaabu/bulk-actions/llms.txt` lists every page in `vendor/javaabu/bulk-actions/docs/`.

| Task | Read |
| --- | --- |
| Add bulk actions to an index page, end to end | This file |
| Every method, option, the request and the React props | [references/api.md](references/api.md) |

## 1. The class

```php
namespace App\BulkActions;

use App\Enums\ArticleStatuses;
use App\Filters\ArticleFilters;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\Actions\ForceDeleteAction;
use Javaabu\BulkActions\Actions\RestoreAction;
use Javaabu\BulkActions\BulkActions;
use Javaabu\Stencil\Fields\Select;

class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected function setUp(): void
    {
        $this->filters(ArticleFilters::class);
    }

    public function newQuery(): Builder
    {
        return Article::query()->whereBelongsTo(request()->user()->organization);
    }

    protected function actions(): array
    {
        return [
            DeleteAction::make()->can('delete_articles'),
            RestoreAction::make()->can('delete_articles'),
            ForceDeleteAction::make()->can('force_delete_articles'),

            Action::make('publish')
                ->label(__('Publish'))
                ->icon('check')
                ->requiresConfirmation(__('Publish the selected articles?'))
                ->successMessage(__(':count articles published.'))
                ->can('publish_articles')
                ->canEach('update')
                ->handle(fn (Collection $articles) => $articles->each->publish()),

            Action::make('change_status')
                ->label(__('Change status'))
                ->can('edit_articles')
                ->canEach('update')
                ->form([
                    Select::make('status')->label(__('Status'))->options(ArticleStatuses::class)->required(),
                ])
                ->each(fn (Article $article, array $data) => $article->update(['status' => $data['status']])),
        ];
    }
}
```

## 2. Routes

```php
Route::middleware(['web', 'auth:web_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::bulkActions('articles', ArticleBulkActions::class, ['guard' => 'web_admin']);   // POST admin/articles/bulk, admin.articles.bulk
    Route::bulkActionRuns(['guard' => 'web_admin', 'prefix' => 'admin/bulk-actions/runs']);
});
```

## 3a. Blade

```php
return view('admin.articles.index', [
    'articles' => $articles,
    'bulk_actions' => ArticleBulkActions::make(),
]);
```

```blade
<x-forms::table model="articles">
    <x-slot:bulk-form :action="$bulk_actions->getUrl()">
        <x-bulk-actions::actions :actions="$bulk_actions" :paginator="$articles" />
    </x-slot:bulk-form>
    <x-slot:headers>…</x-slot:headers>
    <x-slot:rows>
        @foreach($articles as $article)
            <x-forms::table.row :model="$article" name="articles">…</x-forms::table.row>
        @endforeach
    </x-slot:rows>
</x-forms::table>
```

## 3b. Inertia React

```php
return Inertia::render('Articles/Index', [
    'articles' => $articles,
    'bulkActions' => ArticleBulkActions::make()->paginator($articles),
]);
```

```tsx
import { router } from "@inertiajs/react"
import { TableRow, TableCell, TableHeading } from "@javaabu/forms"
import { BulkActionsTable } from "@javaabu/bulk-actions"

<BulkActionsTable bulkActions={bulkActions} onRunFinished={() => router.reload()} headers={<TableHeading label="ސުރުހީ" />}>
  {articles.data.map((article) => (
    <TableRow key={article.id} model={article}>
      <TableCell name="title" />
    </TableRow>
  ))}
</BulkActionsTable>
```

## 4. Test

```php
#[Test]
public function it_skips_articles_the_author_cannot_delete(): void
{
    $author = $this->author();
    $mine = Article::factory()->for($author)->create(['title' => 'ތިމާގެ']);
    $theirs = Article::factory()->create(['title' => 'އެހެން']);

    $this->actingAs($author)
        ->postJson(route('admin.articles.bulk'), ['action' => 'delete', 'articles' => [$mine->id, $theirs->id]])
        ->assertOk()
        ->assertJson(['succeeded' => 1, 'skipped' => 1]);

    $this->assertSoftDeleted('articles', ['id' => $mine->id]);
}
```

## Rules

- `can()` = who may run the action (hidden from others, 403 on submit). `canEach()` = per record, through the policy; failures are skipped and reported. `visible()` is presentation only.
- Scope records in `newQuery()`; ids outside it are ignored. Don't filter by permission inside the handler.
- Handlers: `handle(fn (Collection $records, array $data, $user))` per chunk, or `each(fn (Article $article, array $data))` per record. Return a count from `handle()` only when some failed.
- The table's `model`, the rows' `name` and the bulk actions' `getName()` must match (`articles`).
- Queued runs need a class (not inline `BulkActions::make()`), a queue worker and `Route::bulkActionRuns()`. Validated form data is stored as JSON: no file uploads in queued actions (`->queue(false)`).
- Pass the paginator (`:paginator` / `->paginator()`) to offer "select all matching", and `filters()` so "all" means the filtered listing, not the whole table.
- Every label, question and message through `__()`; Dhivehi in `lang/dv.json`; `->thaana()` on Dhivehi text fields.

## Common mistakes

| Mistake | Instead |
| --- | --- |
| Writing a `bulk()` controller with `switch ($request->action)` | An action per case in a BulkActions class, `Route::bulkActions()` |
| `->hidden(fn () => ! $user->can(...))` | `->can('permission')` |
| Only `canEach()` on an action some users may never run | Add `can()` so it's hidden from them |
| `Article::whereIn('id', $request->ids)` in a handler | The handler already gets the selected, authorized records |
| Naming a form field `action` or `all` | Any other name |
| Inline `BulkActions::make([...])` for a large table | A class, so big selections can be queued |
| Loading routes automatically or assuming the `web` guard | Register routes in the app's group; pass `guard` |
