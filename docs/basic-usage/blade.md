---
title: Blade
sidebar_position: 5
---

The Blade UI goes in the `bulk-form` slot of javaabu/forms v2's `<x-forms::table>`, which wraps the table in a form posting to the slot's `action`. The rows' checkboxes (`articles[]`) and the select-all checkbox are the table's own.

```php
// ArticlesController
public function index(Request $request): View
{
    $articles = Article::query()->latest()->paginate(20)->withQueryString();

    return view('articles.index', [
        'articles' => $articles,
        'bulk_actions' => ArticleBulkActions::make(),
    ]);
}
```

```blade
<x-forms::table model="articles">
    <x-slot:bulk-form :action="$bulk_actions->getUrl()">
        <x-bulk-actions::actions :actions="$bulk_actions" :paginator="$articles" />
    </x-slot:bulk-form>

    <x-slot:headers>
        <x-forms::table.heading :label="__('Title')" sortable="title" />
        <x-forms::table.heading :label="__('Status')" />
    </x-slot:headers>

    <x-slot:rows>
        @foreach($articles as $article)
            <x-forms::table.row :model="$article" name="articles">
                <x-forms::table.cell name="title" />
                <x-forms::table.cell name="status" />
            </x-forms::table.row>
        @endforeach
    </x-slot:rows>

    <x-slot:pagination>
        {{ $articles->links() }}
    </x-slot:pagination>
</x-forms::table>
```

The table's `model` and the rows' `name` must be the bulk actions' name (`$bulk_actions->getName()`).

# What it shows

- The result of the last action, as an alert: green when everything succeeded, amber when some records were skipped or failed. For a queued run it shows a progress bar that updates until the run finishes.
- An action select (only the actions the user may run) and an Apply button.
- How many rows are selected.
- When every row on the page is selected and there are more, "Select all N", which switches to every record matching the listing. "Clear selection" goes back.
- For actions with a confirmation or a form, a dialog (`<x-forms::modal>`) with the question, the action's stencil fields, and Cancel and confirm buttons. After a validation error, the dialog opens again with the errors.

# Attributes

| Attribute | | |
| --- | --- | --- |
| `actions` | `BulkActions` | Required |
| `paginator` | `Paginator` | The listing's paginator: the total for "select all matching" and the page size |
| `total` | `int` | The total, without a paginator. Leave both out to hide "select all matching" |
| `framework` | `string` | javaabu/forms' framework, default `config('forms.framework')` |

`$bulk_actions->render()` renders the same view (or returns the React props when `bulk-actions.views` is `inertia`), for packages that serve both stacks.

# The script

The component requires the `bulk-actions::bulk-actions` script through javaabu/manifest, which injects it (after javaabu/forms' own script) into the page. It counts the selection, offers "select all matching", opens the dialogs, and polls queued runs. It sets up anything added to the page later, too. With a bundler:

```js
import { init } from "@javaabu/bulk-actions/blade/bulk-actions"

init(document)
```

Set `bulk-actions.assets` to `false` to include it yourself. It dispatches `bulk-actions:finished` on the result alert when a queued run finishes, with the run's status as `detail`:

```js
document.addEventListener("bulk-actions:finished", () => window.location.reload())
```

# Without JavaScript

Actions without a dialog work without JavaScript: the form posts and the page comes back with the result. Actions with a dialog need the script.

# Customizing

Publish the view with `php artisan vendor:publish --tag=bulk-actions-views`. See [Overriding controllers and views](../advanced-usage/overriding-controllers-and-views.md).
