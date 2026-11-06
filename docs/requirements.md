---
title: Requirements
sidebar_position: 1.1
---

This package requires:

- PHP 8.4 or higher
- Laravel 12 or 13
- [javaabu/forms](https://github.com/Javaabu/forms) v2, for the Blade table and components (installed with bulk-actions)
- [javaabu/stencil](https://github.com/Javaabu/stencil), for action forms (installed with bulk-actions)
- [javaabu/permissions](https://github.com/Javaabu/permissions) v3, which registers the package's policy and permissions (installed with bulk-actions)
- [javaabu/manifest](https://github.com/Javaabu/manifest), which ships the Blade script and the React components to your app (installed with bulk-actions)
- A queue worker, for [queued runs](./advanced-usage/queued-runs.md). With the `sync` driver, large selections run during the request.

For Inertia React pages (Blade-only apps need none of this):

- React 19
- [@javaabu/forms](https://github.com/Javaabu/react-forms) and its peer dependencies (`php artisan manifest:check` lists what's missing)
- manifest's Vite plugin, which lets your app import `@javaabu/bulk-actions` and `@javaabu/stencil`

Optional:

- [javaabu/filters](https://github.com/Javaabu/filters) v1, to [select all records matching the current filters](./advanced-usage/selecting-all-matching.md) with your filters class. Without it, pass a closure.
