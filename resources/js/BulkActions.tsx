import * as React from "react"

import { Alert, Button, Form, Modal, Select, Table, type FormValues, type TableProps } from "@javaabu/forms"
import { StencilFields, type StencilComponents } from "@javaabu/stencil"

import { cancelRun, flattenQuery, formatNumber, percent, pollRun, replace } from "./support"
import type { BulkAction, BulkActionResult, BulkActionRunStatus, BulkActionsSchema } from "./types"

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

/** What is about to be submitted: the action and the selection. */
export interface PendingBulkAction {
  action: BulkAction
  ids: string[]
  all: boolean
}

export interface BulkActionsState {
  schema: BulkActionsSchema
  /** "All matching" is selected. */
  all: boolean
  setAll: (all: boolean) => void
  /** The action being confirmed or submitted. */
  pending: PendingBulkAction | null
  setPending: (pending: PendingBulkAction | null) => void
  /** A message for the bar: no action chosen, nothing selected. */
  error: string | null
  setError: (error: string | null) => void
  /** Handles the bulk form's submit: opens the action's dialog, or submits it. */
  apply: (values: FormValues) => void
}

function toList(value: FormDataEntryValue | FormDataEntryValue[] | undefined): string[] {
  if (value === undefined) return []
  return (Array.isArray(value) ? value : [value]).filter(
    (item): item is string => typeof item === "string" && item !== "",
  )
}

/** The state of a bulk actions table. `BulkActionsTable` uses it; use it to build your own. */
export function useBulkActions(schema: BulkActionsSchema): BulkActionsState {
  const [all, setAll] = React.useState(schema.old.all)
  const [pending, setPending] = React.useState<PendingBulkAction | null>(null)
  const [error, setError] = React.useState<string | null>(null)

  const apply = React.useCallback(
    (values: FormValues) => {
      const name = typeof values.action === "string" ? values.action : ""
      const action = schema.actions.find((candidate) => candidate.name === name)

      if (!action) {
        setError(schema.strings.choose_action)
        return
      }

      const ids = toList(values[`${schema.name}[]`])

      if (!all && ids.length === 0) {
        setError(schema.strings.none_selected)
        return
      }

      setError(null)
      setPending({ action, ids, all })
    },
    [schema, all],
  )

  return { schema, all, setAll, pending, setPending, error, setError, apply }
}

/* -------------------------------------------------------------------------- */
/* Selection                                                                  */
/* -------------------------------------------------------------------------- */

/**
 * How many rows are checked in the form around `ref`, read from the form's data,
 * so it works with every @javaabu/forms adapter's checkbox.
 */
export function useCheckedCount(
  ref: React.RefObject<HTMLElement | null>,
  name: string,
): { checked: number; rows: number } {
  const [count, setCount] = React.useState({ checked: 0, rows: 0 })

  React.useEffect(() => {
    const form = ref.current?.closest("form")
    if (!form) return

    const field = `${name}[]`
    const read = () => {
      const rows = [...form.querySelectorAll<HTMLInputElement>("input")].filter(
        (input) => input.name === field && !input.disabled,
      ).length
      const checked = new FormData(form).getAll(field).length
      setCount((current) => (current.checked === checked && current.rows === rows ? current : { checked, rows }))
    }
    // Checkboxes update after the event, so read on the next tick.
    const schedule = () => setTimeout(read, 0)

    read()
    form.addEventListener("change", schedule)
    form.addEventListener("click", schedule)

    return () => {
      form.removeEventListener("change", schedule)
      form.removeEventListener("click", schedule)
    }
  }, [ref, name])

  return count
}

/* -------------------------------------------------------------------------- */
/* Bar                                                                        */
/* -------------------------------------------------------------------------- */

export interface BulkActionsBarProps {
  state: BulkActionsState
  className?: string
}

/** The action select, Apply button, selected count and "select all matching". Goes in `Table bulkForm`. */
export function BulkActionsBar({ state, className }: BulkActionsBarProps) {
  const { schema, all, setAll, error } = state
  const { strings, total } = schema
  const ref = React.useRef<HTMLDivElement>(null)
  const { checked, rows } = useCheckedCount(ref, schema.name)

  React.useEffect(() => {
    if (all && rows > 0 && checked < rows) setAll(false)
  }, [all, checked, rows, setAll])

  if (schema.actions.length === 0) return null

  const options = Object.fromEntries(schema.actions.map((action) => [action.name, action.label]))
  const offer = total !== null && rows > 0 && checked === rows && total > checked
  const count =
    all && total !== null
      ? replace(strings.selected, { count: formatNumber(total) })
      : checked > 0
        ? replace(strings.selected, { count: formatNumber(checked) })
        : strings.none_selected

  return (
    <div ref={ref} className={className ?? "flex flex-col gap-3"} data-bulk-actions={schema.name}>
      <div className="flex flex-wrap items-start gap-2">
        <div className="w-full md:w-64">
          <Select
            name="action"
            options={options}
            placeholder={strings.choose_action}
            showPlaceholder
            label={strings.actions}
            showLabel={false}
            default={schema.old.action ?? undefined}
          />
        </div>
        <Button type="submit" color="warning">
          {strings.apply}
        </Button>
        <p className="self-center text-sm text-muted-foreground" aria-live="polite" role={error ? "alert" : undefined}>
          {error ?? count}
        </p>
      </div>

      {all || offer ? (
        <div
          className="flex flex-wrap items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm"
          data-bulk-actions-all-matching
        >
          {all ? (
            <>
              <span>{replace(strings.all_selected, { total: formatNumber(total ?? 0) })}</span>
              <button type="button" className="font-medium underline underline-offset-4" onClick={() => setAll(false)}>
                {strings.clear_selection}
              </button>
            </>
          ) : (
            <>
              <span>{replace(strings.all_on_page_selected, { count: formatNumber(checked) })}</span>
              <button type="button" className="font-medium underline underline-offset-4" onClick={() => setAll(true)}>
                {replace(strings.select_all_matching, { total: formatNumber(total ?? 0) })}
              </button>
            </>
          )}
        </div>
      ) : null}
    </div>
  )
}

/* -------------------------------------------------------------------------- */
/* Submitting                                                                 */
/* -------------------------------------------------------------------------- */

export interface BulkActionFormProps {
  state: BulkActionsState
  /** Components for the stencil form's node types. */
  components?: StencilComponents
}

function currentUrl(): string {
  return typeof window === "undefined" ? "" : window.location.href
}

/**
 * Submits the pending action: in a dialog for actions with a confirmation or a
 * form, otherwise straight away. Submits through @javaabu/forms' `Form`, so it
 * follows the `FormsProvider` router (an Inertia visit in Inertia apps).
 */
export function BulkActionForm({ state, components }: BulkActionFormProps) {
  const { schema, pending, setPending } = state
  const id = `${schema.name}-bulk-action-form`
  const action = pending?.action
  const needsDialog = !!action && (!!action.confirmation || !!action.form)

  React.useEffect(() => {
    if (!pending || needsDialog) return
    const form = document.getElementById(id)
    if (form instanceof HTMLFormElement) form.requestSubmit()
  }, [pending, needsDialog, id])

  if (!pending || !action) return null

  const close = () => setPending(null)

  const fields = (
    <>
      <input type="hidden" name="action" value={action.name} />
      {pending.all ? (
        <>
          <input type="hidden" name="all" value="1" />
          {flattenQuery(schema.filters, "filters").map(([name, value]) => (
            <input key={name} type="hidden" name={name} value={value} />
          ))}
        </>
      ) : (
        pending.ids.map((value) => <input key={value} type="hidden" name={`${schema.name}[]`} value={value} />)
      )}
      <input type="hidden" name="redirect" value={currentUrl()} />
    </>
  )

  const form = (
    <Form
      id={id}
      action={schema.url}
      method="post"
      preserveScroll
      preserveState="errors"
      onSuccess={close}
      hidden={!needsDialog}
      className="flex flex-col gap-4"
    >
      {fields}
      {action.confirmation?.text ? <p>{action.confirmation.text}</p> : null}
      {action.form ? <StencilFields schema={action.form} components={components} /> : null}
      {needsDialog ? (
        <div className="flex justify-end gap-2" data-slot="modal-footer">
          <Button color="secondary" onClick={close}>
            {schema.strings.cancel}
          </Button>
          <Button type="submit" color={action.color as never} animate>
            {action.confirmation?.button ?? schema.strings.confirm}
          </Button>
        </div>
      ) : null}
    </Form>
  )

  if (!needsDialog) return form

  return (
    <Modal
      open
      onOpenChange={(open) => (open ? null : close())}
      title={action.confirmation?.heading ?? action.label}
      size="md"
    >
      {form}
    </Modal>
  )
}

/* -------------------------------------------------------------------------- */
/* Result and progress                                                        */
/* -------------------------------------------------------------------------- */

/** Follows a queued run: its latest status, polled until it finishes. */
export function useRunStatus(result: BulkActionResult | null, interval: number): BulkActionRunStatus | null {
  const [latest, setLatest] = React.useState<{ url: string; status: BulkActionRunStatus } | null>(null)
  const url = result?.status === "queued" ? result.run_url : null

  React.useEffect(() => {
    if (!url) return
    const controller = new AbortController()
    pollRun(url, interval, (status) => setLatest({ url, status }), controller.signal)
    return () => controller.abort()
  }, [url, interval])

  return latest && latest.url === url ? latest.status : null
}

export interface BulkActionsResultProps {
  schema: BulkActionsSchema
  /** Called once a queued run finishes, e.g. to reload the listing. */
  onRunFinished?: (status: BulkActionRunStatus) => void
  className?: string
}

/** The result of the last action, with progress and a Stop button while a queued run is going. */
export function BulkActionsResult({ schema, onRunFinished, className }: BulkActionsResultProps) {
  const { result, strings } = schema
  const status = useRunStatus(result, schema.pollInterval)
  const [dismissedResult, setDismissedResult] = React.useState<BulkActionResult | null>(null)
  const dismissed = dismissedResult !== null && dismissedResult === result
  const finished = React.useRef<string | null>(null)

  React.useEffect(() => {
    if (!status?.is_finished || finished.current === status.id) return
    finished.current = status.id
    onRunFinished?.(status)
  }, [status, onRunFinished])

  if (!result || dismissed) return null

  const current = status?.result ?? result
  const running = current.status === "queued"
  const processed = status ? status.processed : current.succeeded + current.skipped + current.failed
  const total = status ? status.total : current.total
  const cancelUrl = status?.cancel_url ?? null

  return (
    <Alert type={current.tone} className={className}>
      <div className="flex flex-col gap-2">
        <span>{status?.message ?? current.message}</span>
        {running ? (
          <span className="flex flex-wrap items-center gap-2">
            <progress
              className="h-2 w-full max-w-xs"
              max={100}
              value={status?.progress ?? percent(processed, total)}
              aria-label={strings.queued}
            />
            <span className="text-sm">
              {replace(strings.progress, { processed: formatNumber(processed), total: formatNumber(total) })}
            </span>
            {cancelUrl ? (
              <Button size="sm" color="outline-secondary" onClick={() => void cancelRun(cancelUrl)}>
                {strings.cancel_run}
              </Button>
            ) : null}
          </span>
        ) : (
          <span>
            <Button size="sm" color="link" onClick={() => setDismissedResult(result)}>
              {strings.dismiss}
            </Button>
          </span>
        )}
      </div>
    </Alert>
  )
}

/* -------------------------------------------------------------------------- */
/* Table                                                                      */
/* -------------------------------------------------------------------------- */

export interface BulkActionsTableProps extends Omit<TableProps, "model" | "bulkForm" | "bulkFormAction"> {
  /** `$bulkActions->toArray()`: pass the BulkActions as an Inertia prop. */
  bulkActions: BulkActionsSchema
  /** Components for the action forms' stencil node types. */
  components?: StencilComponents
  /** Called once a queued run finishes, e.g. `() => router.reload()`. */
  onRunFinished?: (status: BulkActionRunStatus) => void
}

/**
 * @javaabu/forms' `Table` with bulk actions: the result of the last action,
 * the action bar in the table's bulk form, row selection, "select all
 * matching" and the confirmation and form dialogs.
 */
export function BulkActionsTable({
  bulkActions,
  components,
  onRunFinished,
  bulkFormProps,
  ...props
}: BulkActionsTableProps) {
  const state = useBulkActions(bulkActions)
  const hasActions = bulkActions.actions.length > 0

  return (
    <>
      <BulkActionsResult schema={bulkActions} onRunFinished={onRunFinished} className="mb-4" />
      <Table
        {...props}
        model={bulkActions.name}
        bulkForm={hasActions ? <BulkActionsBar state={state} /> : undefined}
        bulkFormAction={bulkActions.url}
        bulkFormProps={{ ...bulkFormProps, onSubmit: (values: FormValues) => state.apply(values) }}
      />
      <BulkActionForm state={state} components={components} />
    </>
  )
}
