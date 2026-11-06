# Changelog

All notable changes to `bulk-actions` will be documented in this file.

## Unreleased

- Initial release: fluent bulk action definitions with per-action and per-record authorization, stencil forms, chunked and queued execution, and Blade and Inertia React UI.
- Context for queued runs: `withContext()`, `context()` and `static fromContext()` let a set configured at runtime be rebuilt by the job (stored in a new `context` column: run `php artisan migrate`, and publish `bulk-actions-migrations` again if you published them).
- The React-only npm dependencies (`@javaabu/forms`, `react`, `react-dom`) are declared for pages only, so `manifest:check` doesn't ask Blade-only apps for them.
