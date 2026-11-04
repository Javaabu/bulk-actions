---
title: Authorization
sidebar_position: 2
---

Each action is authorized at two levels, both through Laravel's Gate, so your policies and javaabu/permissions' permissions apply as they do everywhere else.

# can(): who may run the action

`can()` works like `can()` in [Javaabu/menu-builder](https://github.com/Javaabu/menu-builder): an ability or permission name, with optional arguments, or a closure.

```php
Action::make('publish')->can('publish_articles');                 // a permission (spatie) or Gate ability
Action::make('publish')->can('publish', Article::class);          // a policy method without a model: publish(User $user)
Action::make('publish')->can(fn (?User $user) => $user?->is_editor);
```

- Users who fail it don't see the action: it's left out of the Blade select and the React props.
- The server checks it again when the action is submitted and responds with **403** when it fails.
- Without a user, a string ability fails. Without `can()`, everyone who can reach the route may run the action.

# canEach(): which records

`canEach()` is checked for every selected record, with the record: `$user->can('update', $article)`. Records that fail are **skipped**, not a 403: the action runs on the rest, and the message says how many were skipped.

```php
Action::make('publish')->canEach('update');
Action::make('publish')->canEach(fn (User $user, Article $record) => $record->author()->is($user));
```

The built-in actions check `delete`, `restore` and `forceDelete`, so they follow the model's policy. With javaabu/permissions' `ResourcePolicy`, that's the `delete_articles`, `force_delete_articles` permissions, and ownership with `$owned = true`.

# Use both

`canEach()` alone can't hide an action, since there are no records when the page renders. Add `can()` for that:

```php
DeleteAction::make()->can('delete_articles');                    // hidden from users without the permission

Action::make('publish')
    ->can('publish_articles')                                     // who sees it
    ->canEach('update');                                          // which of the selected articles
```

# visible() is not a permission check

`visible()` and `hidden()` only decide whether the action is listed. A hidden action can still be submitted, so never use them for security: use `can()`.

# Scoping the records

Authorization decides who may run an action on a record. To decide which records may be selected at all (a tenant, an organization), narrow the [query](./defining-actions.md#the-records): ids outside it are ignored.

# The user

Actions are authorized for the request's user (`$request->user()`), so the route's guard applies. In a queued run, they're authorized for the user who started it. Set another with `->user($user)`.

# Routes

`Route::bulkActions()` adds the `auth` middleware (or `auth:{guard}`) by default, so guests can't reach the endpoint. See [Running actions](./running-actions.md#routes).
