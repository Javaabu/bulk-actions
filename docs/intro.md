---
title: Introduction
sidebar_position: 1.0
---

# Bulk Actions

[Bulk Actions](https://github.com/Javaabu/bulk-actions) adds bulk actions to Laravel index pages: select some rows (or every record matching the current filters), pick an action, confirm, and it runs.

- **Defined once, fluently.** Each action has a label, an icon, an optional confirmation and a success message. Delete, restore and force delete are built in.
- **Authorized through Laravel's Gate.** `can()` decides who sees and may run an action. `canEach()` checks every record against your policy and skips the ones the user may not touch.
- **Input with stencil.** An action can ask for input (a new status, a user to assign) with a [stencil](https://github.com/Javaabu/stencil) form, validated before it runs.
- **Any size.** Records are handled in chunks. Large selections run on the queue, with progress the page polls and a Stop button.
- **Select all matching.** "All matching" rebuilds the listing's query from its filters, with [javaabu/filters](https://github.com/Javaabu/filters) or your own closure.
- **Blade and Inertia React.** The selection and action UI is built on javaabu/forms' table in Blade, and on @javaabu/forms' `Table` in React.
- **Dhivehi and right to left.** Every string has a Dhivehi translation, and the views use logical directions.

```php
use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\BulkActions;
use Javaabu\Stencil\Fields\Select;

class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected function actions(): array
    {
        return [
            DeleteAction::make(),

            Action::make('publish')
                ->label(__('Publish'))
                ->can('publish_articles')
                ->canEach('update')
                ->requiresConfirmation()
                ->handle(fn (Collection $articles) => $articles->each->publish()),

            Action::make('change_status')
                ->label(__('Change status'))
                ->canEach('update')
                ->form([
                    Select::make('status')->options(ArticleStatuses::class)->required(),
                ])
                ->each(fn (Article $article, array $data) => $article->update($data)),
        ];
    }
}
```

```php
// routes/web.php
Route::bulkActions('articles', ArticleBulkActions::class);   // POST articles/bulk
Route::bulkActionRuns();                                      // progress of queued runs
```

```blade
<x-forms::table model="articles">
    <x-slot:bulk-form :action="$bulk_actions->getUrl()">
        <x-bulk-actions::actions :actions="$bulk_actions" :paginator="$articles" />
    </x-slot:bulk-form>
    {{-- headers and rows --}}
</x-forms::table>
```

```tsx
import { BulkActionsTable } from "@javaabu/bulk-actions"

<BulkActionsTable bulkActions={bulkActions} headers={…}>{/* rows */}</BulkActionsTable>
```

# Where to next

- [Installation & Setup](./installation-and-setup.md)
- [Defining actions](./basic-usage/defining-actions.md)
- [Authorization](./basic-usage/authorization.md)
- [Actions with forms](./basic-usage/actions-with-forms.md)
- [Running actions](./basic-usage/running-actions.md)
- [Blade](./basic-usage/blade.md) and [Inertia React](./basic-usage/react.md)
- [Selecting all matching records](./advanced-usage/selecting-all-matching.md)
- [Queued runs](./advanced-usage/queued-runs.md)
