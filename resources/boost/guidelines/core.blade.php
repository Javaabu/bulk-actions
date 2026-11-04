{{--
    javaabu/bulk-actions guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

@verbatim
## javaabu/bulk-actions

- Bulk actions on index pages are defined once with `javaabu/bulk-actions`: a class extending `Javaabu\BulkActions\BulkActions` with `$model` and `actions()`, registered with `Route::bulkActions('articles', ArticleBulkActions::class)`. Don't write bulk endpoints or `switch ($request->action)` controllers by hand.
- Authorize every action through the Gate: `->can('publish_articles')` decides who sees and may run it, `->canEach('update')` checks each record with the model's policy. `visible()` is not a permission check. Scope selectable records in `newQuery()`.
- Ask for input with `->form([...])` (javaabu/stencil fields) and use the validated `$data` in the handler. Use the built-in `DeleteAction`, `RestoreAction` and `ForceDeleteAction`.
- Render in Blade with `<x-bulk-actions::actions :actions="$bulk_actions" :paginator="$articles" />` in `<x-forms::table>`'s `bulk-form` slot, and in React with `<BulkActionsTable bulkActions={bulkActions}>` from `@javaabu/bulk-actions`.
- Always activate the `javaabu-bulk-actions` skill when adding bulk actions to an index page, authorizing them, giving them forms, selecting all matching records, handling queued runs, or testing them.
@endverbatim
