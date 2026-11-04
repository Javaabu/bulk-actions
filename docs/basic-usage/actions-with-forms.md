---
title: Actions with forms
sidebar_position: 3
---

An action can ask for input before it runs: a new status, a user to assign, a reason. Define the fields with [stencil](https://github.com/Javaabu/stencil); they're shown in the action's dialog, validated on the server, and passed to the handler as `$data`.

```php
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;

Action::make('change_status')
    ->label(__('Change status'))
    ->canEach('update')
    ->form([
        Select::make('status')->options(ArticleStatuses::class)->required(),
        Textarea::make('note')->label(__('Note'))->thaana(),
    ])
    ->each(fn (Article $article, array $data) => $article->update(['status' => $data['status']]));
```

- `form()` takes stencil components, a closure that returns them, or a stencil `Form`. It's rendered embedded (no `<form>` tag or submit button) with the id `{name}-{action}-form`.
- The form is validated with the rules stencil derives from the schema (`required()`, options, `maxLength()`, …) plus your `->rules()`. A failed validation sends the user back to the dialog with the errors.
- `$data` holds the validated values of the fields only, so other request input never reaches the handler.
- Only the chosen action's form is validated, and in Blade only its fields are submitted.

# Field names

The action's fields are submitted next to the bulk action's own fields, so don't name a field `action`, `all`, `filters`, `redirect`, or the selection's name (`articles`).

# Relationships and options

Everything stencil supports works, such as a select of users:

```php
Action::make('assign')
    ->form([
        Select::make('user_id')->label(__('Assign to'))->options(fn () => User::query()->pluck('name', 'id'))->required(),
    ])
    ->each(fn (Ticket $ticket, array $data) => $ticket->assignee()->associate($data['user_id'])->save());
```

Live fields (`->live()`) aren't supported in action forms: use declarative conditions such as `visibleWhen()` instead.

# Queued actions

The validated `$data` is stored with a [queued run](../advanced-usage/queued-runs.md) as JSON and handed to the job. File uploads can't be queued; give such actions `->queue(false)`.

# Thaana

Add `->thaana()` to text fields that take Dhivehi, as anywhere in stencil.
