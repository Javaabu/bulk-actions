---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

Bulk Actions and its Javaabu dependencies aren't on Packagist yet. Add their repositories, then require the package:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Javaabu/bulk-actions" },
    { "type": "vcs", "url": "https://github.com/Javaabu/stencil" },
    { "type": "vcs", "url": "https://github.com/Javaabu/permissions" },
    { "type": "vcs", "url": "https://github.com/Javaabu/forms" },
    { "type": "vcs", "url": "https://github.com/Javaabu/manifest" }
]
```

```bash
composer require javaabu/bulk-actions:^1.0@dev
php artisan migrate                # the bulk_action_runs table, for queued runs
php artisan permissions:sync       # view_others_bulk_action_runs, cancel_others_bulk_action_runs
```

The service provider registers itself. Nothing needs configuring for the common case.

# Routes

Routes aren't loaded automatically: register them inside your own route group, so you choose the prefix, the middleware and the guard.

```php
// routes/web.php
use App\BulkActions\ArticleBulkActions;

Route::middleware(['web', 'auth'])->group(function () {
    Route::bulkActions('articles', ArticleBulkActions::class);   // POST /articles/bulk, name articles.bulk
    Route::bulkActionRuns();                                      // GET /bulk-actions/runs/{run}, POST …/cancel
});
```

For an admin guard:

```php
Route::prefix('admin')->name('admin.')->middleware(['web', 'auth:web_admin'])->group(function () {
    Route::bulkActions('articles', ArticleBulkActions::class, ['guard' => 'web_admin']);
    Route::bulkActionRuns(['guard' => 'web_admin', 'prefix' => 'admin/bulk-actions/runs']);
});
```

See [Running actions](./basic-usage/running-actions.md#routes) for every option.

# Blade

The Blade view is built on javaabu/forms v2's table, and its script is added to the page through javaabu/manifest when the view renders. If your app already uses javaabu/forms, there's nothing more to do.

# Inertia React

The React components are in the Composer package, under `resources/js`, and your app builds them with its own Vite through [javaabu/manifest](https://github.com/Javaabu/manifest), like stencil's renderer. If your app already renders stencil forms in React, there's nothing more to do. Otherwise add manifest's Vite plugin:

```js
// vite.config.js
import { defineConfig } from "vite"
import laravel from "laravel-vite-plugin"
import react from "@vitejs/plugin-react"
import manifest from "./vendor/javaabu/manifest/dist/vite.js"

export default defineConfig({
  plugins: [laravel({ input: ["resources/js/app.tsx"] }), react(), manifest()],
})
```

```bash
npm install github:Javaabu/react-forms#next-gen react react-dom
php artisan manifest:check     # lists anything missing
php artisan manifest:types     # TypeScript paths for @javaabu/bulk-actions and @javaabu/stencil
```

Set up `FormsProvider` once with the Inertia router, so actions submit as Inertia visits:

```tsx
// resources/js/app.tsx
import { createInertiaApp } from "@inertiajs/react"
import { createRoot } from "react-dom/client"
import { FormsProvider, mergeAdapters } from "@javaabu/forms"
import { inertia, inertiaLinks } from "@javaabu/forms/inertia"
import { onu } from "@javaabu/forms/onu"

const ui = mergeAdapters(onu, inertiaLinks)

createInertiaApp({
  resolve: (name) => import(`./pages/${name}.tsx`),
  setup({ el, App, props }) {
    createRoot(el).render(
      <FormsProvider ui={ui} router={inertia}>
        <App {...props} />
      </FormsProvider>,
    )
  },
})
```

# Queue

Selections of more than 500 records (`bulk-actions.queue_after`) run on the queue. Run a worker in production:

```bash
php artisan queue:work
```

Schedule pruning of finished runs:

```php
// routes/console.php
use Javaabu\BulkActions\Models\BulkActionRun;

Schedule::command('model:prune', ['--model' => [BulkActionRun::class]])->daily();
```

# Publishing

| Tag | What |
| --- | --- |
| `bulk-actions-config` | `config/bulk-actions.php` |
| `bulk-actions-migrations` | The `bulk_action_runs` migration. Call `BulkActions::$runs_migrations = false` in a service provider so the package's copy isn't loaded too. |
| `bulk-actions-translations` | `lang/vendor/bulk-actions` |
| `bulk-actions-views` | `resources/views/vendor/bulk-actions` |

```bash
php artisan vendor:publish --tag=bulk-actions-config
```

Override a React component with `php artisan manifest:override bulk-actions js/BulkActions.tsx`.
