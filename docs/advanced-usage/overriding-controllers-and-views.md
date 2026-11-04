---
title: Overriding controllers and views
sidebar_position: 4
---

# Controllers

Extend a controller and set it in the config, or pass it to the route macro:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Javaabu\BulkActions\Http\Controllers\BulkActionsController;
use Symfony\Component\HttpFoundation\Response;

class ArticleBulkActionsController extends BulkActionsController
{
    public function store(Request $request): Response
    {
        $response = parent::store($request);

        cache()->forget('articles.count');

        return $response;
    }
}
```

```php
// config/bulk-actions.php
'controllers' => [
    'bulk_actions' => \App\Http\Controllers\ArticleBulkActionsController::class,
],

// or for one route
Route::bulkActions('articles', ArticleBulkActions::class, ['controller' => ArticleBulkActionsController::class]);
```

`BulkActionRunsController` has `show()` and `cancel()`.

# The policy

```php
// config/permissions.php
'policies' => [
    \Javaabu\BulkActions\Models\BulkActionRun::class => \App\Policies\BulkActionRunPolicy::class,
],
```

# Blade views

```bash
php artisan vendor:publish --tag=bulk-actions-views
```

copies `actions.blade.php` to `resources/views/vendor/bulk-actions`. It gets `$bulkActions` and `$framework`. Keep the `data-bulk-actions-*` attributes the script uses: `data-bulk-actions` and `data-bulk-actions-config` on the root, `-count`, `-all`, `-filters`, `-all-matching`, `-page-selected`, `-all-selected`, `-select-all`, `-clear`, `-dialog`, `-fields`, `-confirm`, `-result` and `data-run-url`. Use logical utilities (`ms-`, `pe-`, `text-start`) so the view works right to left.

# React components

```bash
php artisan manifest:override bulk-actions js/BulkActions.tsx
```

copies the file to `resources/vendor/bulk-actions/js/BulkActions.tsx`, where your Vite build picks it up instead of the package's. Or build your own layout from the exported parts (see [Inertia React](../basic-usage/react.md#the-parts)).
