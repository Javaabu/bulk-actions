---
title: Selecting all matching records
sidebar_position: 1
---

When every row on the page is selected and the listing has more, the UI offers "Select all N". The request then sends `all=1` instead of ids, with the listing's query string as `filters[...]`, and the server rebuilds the same query to find the records.

Pass the listing's total, with `:paginator="$articles"` in Blade or `->paginator($articles)` for React, so the UI knows there are more.

# With javaabu/filters

[javaabu/filters](https://github.com/Javaabu/filters) is optional. With it installed, give the bulk actions the listing's filters class:

```php
class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected function setUp(): void
    {
        $this->filters(ArticleFilters::class);
    }
}
```

`filters()` takes the class or an instance. Then:

- The UI sends `$filters->getFilterParameters()`: the applied filters, cleaned (no sort, paging or unknown parameters).
- The server rebuilds the query with `ArticleFilters::make()->fromQuery($filters)->applyFilters($query)`, on top of the bulk actions' own query. Values are cleaned again, so a tampered field can't widen the query beyond what the filters allow, and never beyond the bulk actions' query.

Sorting doesn't matter for a bulk action, so it isn't applied.

:::note
A filters class that reads `request()` or `auth()` in `filters()` gets the bulk action's request (or nothing, in a queued run). Give such a class a setter for what it needs, as the filters docs describe.
:::

# With a closure

Without javaabu/filters, pass a closure that applies the listing's query string to the query:

```php
$this->filters(function (Builder $query, array $filters) {
    return $query
        ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
        ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
});
```

The UI then sends the current query string, without `page`, `per_page`, `orderby` and `order` (`bulk-actions.ignored_parameters`). Choose what's sent yourself with `->filterParameters([...])`.

# Without filters

Without `filters()`, "all" means every record in the bulk actions' query. Only offer it (by passing a total) on listings that aren't filtered, or scope the [query](../basic-usage/defining-actions.md#the-records).

# Authorization

"All matching" doesn't skip authorization: `can()` is checked once and `canEach()` for every record, as with ids.
