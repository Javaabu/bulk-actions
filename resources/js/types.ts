import type { StencilSchema } from "@javaabu/stencil"

/** Asked before an action runs: `confirmation` of an action with requiresConfirmation() or a form. */
export interface BulkActionConfirmation {
  heading: string
  /** The question; null for an action that only has a form. */
  text: string | null
  /** Label of the confirm button. */
  button: string
}

/** One action, as `Action::toArray()` sends it. */
export interface BulkAction {
  name: string
  label: string
  icon: string | null
  /** A javaabu/forms button color: `primary`, `danger`, `warning`, `success`, … */
  color: string
  confirmation: BulkActionConfirmation | null
  /** The action's stencil form (embedded), or null. */
  form: StencilSchema | null
  meta: Record<string, unknown> | null
}

export type BulkActionStatus = "completed" | "queued" | "cancelled" | "failed"

export type BulkActionTone = "success" | "warning" | "danger" | "info"

/** What an action did: `ActionResult::toArray()`. */
export interface BulkActionResult {
  action: string
  status: BulkActionStatus
  tone: BulkActionTone
  total: number
  succeeded: number
  skipped: number
  failed: number
  message: string
  /** The queued run's id. */
  run: string | null
  /** Where the run's status is polled from. */
  run_url: string | null
}

/** A queued run, as `GET {runs}/{run}` returns it under `data`. */
export interface BulkActionRunStatus {
  id: string
  name: string
  action: string
  status: "pending" | "processing" | "completed" | "failed" | "cancelled"
  status_label: string
  progress: number | null
  total: number
  processed: number
  succeeded: number
  skipped: number
  failed: number
  message: string | null
  is_finished: boolean
  is_cancellable: boolean
  cancel_url: string | null
  result: BulkActionResult
}

export interface BulkActionsStrings {
  actions: string
  apply: string
  choose_action: string
  /** `:count selected` */
  selected: string
  none_selected: string
  /** `Select all :total` */
  select_all_matching: string
  /** `All :total records are selected.` */
  all_selected: string
  /** `All :count records on this page are selected.` */
  all_on_page_selected: string
  clear_selection: string
  cancel: string
  confirm: string
  running: string
  queued: string
  /** `:processed of :total done` */
  progress: string
  cancel_run: string
  dismiss: string
}

/** Everything the UI needs: `$bulkActions->toArray()`, passed as an Inertia prop. */
export interface BulkActionsSchema {
  /** Name of the row checkboxes (`articles` for `articles[]`): the table's `model`. */
  name: string
  /** Where the bulk action form posts. */
  url: string
  /** The actions the user may run. */
  actions: BulkAction[]
  /** Records matching the listing, for "select all matching". */
  total: number | null
  /** Records on this page. */
  pageCount: number | null
  /** The listing's query string, sent back for "select all matching". */
  filters: Record<string, unknown>
  /** The result of the last action, flashed after it ran. */
  result: BulkActionResult | null
  old: { action: string | null; all: boolean; ids: string[] }
  /** Milliseconds between status requests for a queued run. */
  pollInterval: number
  /** UI strings, translated on the server. */
  strings: BulkActionsStrings
}
