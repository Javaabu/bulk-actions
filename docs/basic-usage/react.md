---
title: Inertia React
sidebar_position: 6
---

Pass the bulk actions to the page as a prop and render `BulkActionsTable`, which is @javaabu/forms' `Table` with the bulk actions wired in.

```php
// ArticlesController
public function index(Request $request): Response
{
    $articles = Article::query()->latest()->paginate(20)->withQueryString();

    return Inertia::render('Articles/Index', [
        'articles' => $articles,
        'bulkActions' => ArticleBulkActions::make()->paginator($articles),
    ]);
}
```

```tsx
// resources/js/pages/Articles/Index.tsx
import { router } from "@inertiajs/react"
import { Pagination, TableCell, TableHeading, TableRow } from "@javaabu/forms"
import { BulkActionsTable, type BulkActionsSchema } from "@javaabu/bulk-actions"

interface Props {
  articles: { data: Article[] }
  bulkActions: BulkActionsSchema
}

export default function Index({ articles, bulkActions }: Props) {
  return (
    <BulkActionsTable
      bulkActions={bulkActions}
      onRunFinished={() => router.reload()}
      headers={
        <>
          <TableHeading label="ސުރުހީ" sortable="title" />
          <TableHeading label="ހާލަތު" />
        </>
      }
      pagination={<Pagination paginator={articles} />}
    >
      {articles.data.map((article) => (
        <TableRow key={article.id} model={article}>
          <TableCell name="title" />
          <TableCell name="status" />
        </TableRow>
      ))}
    </BulkActionsTable>
  )
}
```

`paginator()` gives the total for "select all matching" and the page size. `BulkActionsTable` takes every `Table` prop except `model`, `bulkForm` and `bulkFormAction`, plus:

| Prop | | |
| --- | --- | --- |
| `bulkActions` | `BulkActionsSchema` | Required: the prop from `$bulkActions->toArray()` |
| `components` | `StencilComponents` | Components for custom stencil node types in action forms |
| `onRunFinished` | `(status) => void` | Called once a queued run finishes; reload the listing here |

# How it submits

Apply reads the bulk form's values: the action and the checked rows. Actions with a confirmation or a form open a `Modal` with the question and the action's fields (rendered by stencil's `StencilFields`); the rest submit straight away. Either way, they submit through @javaabu/forms' `Form`, so with the Inertia router in `FormsProvider` it's an Inertia visit (`preserveScroll`, and `preserveState: "errors"` so the dialog stays open with validation errors). The server redirects back, and the page's `bulkActions` prop carries the result.

# The parts

Build your own layout from the pieces `BulkActionsTable` uses:

```tsx
import { Table } from "@javaabu/forms"
import { BulkActionForm, BulkActionsBar, BulkActionsResult, useBulkActions } from "@javaabu/bulk-actions"

function Articles({ bulkActions, children }) {
  const state = useBulkActions(bulkActions)

  return (
    <>
      <BulkActionsResult schema={bulkActions} />
      <Table
        model={bulkActions.name}
        bulkForm={<BulkActionsBar state={state} />}
        bulkFormAction={bulkActions.url}
        bulkFormProps={{ onSubmit: state.apply }}
      >
        {children}
      </Table>
      <BulkActionForm state={state} />
    </>
  )
}
```

| Export | |
| --- | --- |
| `useBulkActions(schema)` | State: `all`, `setAll`, `pending` (the action being confirmed), `error`, and `apply(values)` for the bulk form's `onSubmit` |
| `BulkActionsBar` | The action select, Apply, the selected count and "select all matching". Renders inside the bulk form |
| `BulkActionForm` | The dialog, or the hidden form for actions without one. Render it **outside** the table, as forms can't nest |
| `BulkActionsResult` | The last result, with progress and Stop while a run is queued |
| `useRunStatus(result, interval)` | The latest status of a queued run, polled until it finishes |
| `useCheckedCount(ref, name)` | How many rows are checked in the form around `ref` |
| `fetchRun`, `cancelRun`, `pollRun` | The run endpoints |

Types: `BulkActionsSchema`, `BulkAction`, `BulkActionResult`, `BulkActionRunStatus`, `BulkActionsStrings`. The format is described in [the API reference](../advanced-usage/api-reference.md#the-react-props).

# Strings

Every string the components show comes translated from the server in `bulkActions.strings`, so the page needs no locale setup.
