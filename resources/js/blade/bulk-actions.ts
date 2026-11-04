/**
 * javaabu/bulk-actions' script for the Blade view (`<x-bulk-actions::actions>`):
 * the selected count, "select all matching", the confirmation and form dialogs,
 * and progress for queued runs. Selecting rows is javaabu/forms' select-all.
 *
 * Loaded as a script it enhances the page by itself, and bars added later.
 * With a bundler: `import { init } from "@javaabu/bulk-actions/blade/bulk-actions"` and call `init(root)`.
 */
import { formatNumber, percent, pollRun, replace } from "../support"
import type { BulkActionRunStatus, BulkActionsStrings } from "../types"

interface BarConfig {
  name: string
  total: number | null
  pageCount: number | null
  pollInterval: number
  dialogs: string[]
  strings: BulkActionsStrings
}

function parseConfig(element: HTMLElement): BarConfig | null {
  try {
    return JSON.parse(element.getAttribute("data-bulk-actions-config") ?? "") as BarConfig
  } catch {
    return null
  }
}

function openDialog(dialog: HTMLDialogElement): void {
  if (!dialog.open) {
    if (typeof dialog.showModal === "function") dialog.showModal()
    else dialog.setAttribute("open", "")
  }
  dialog.setAttribute("data-state", "open")
}

/** Enables one dialog's fields and opens it; the others stay disabled, so only its fields submit. */
function showDialog(bar: HTMLElement, action: string): HTMLDialogElement | null {
  const dialogs = [...bar.querySelectorAll<HTMLDialogElement>("dialog[data-bulk-actions-dialog]")]
  const dialog = dialogs.find((element) => element.getAttribute("data-bulk-actions-dialog") === action) ?? null

  dialogs.forEach((element) => {
    const fields = element.querySelector<HTMLFieldSetElement>("[data-bulk-actions-fields]")
    if (fields) fields.disabled = element !== dialog
  })

  if (!dialog) return null

  openDialog(dialog)
  dialog.querySelector<HTMLElement>("input:not([type=hidden]), select, textarea")?.focus()

  return dialog
}

/** Follows a queued run in its result alert until it finishes. */
export function initProgress(
  alert: HTMLElement,
  config: Pick<BarConfig, "pollInterval" | "strings">,
): AbortController | null {
  const url = alert.getAttribute("data-run-url")
  if (!url || alert.hasAttribute("data-bulk-actions-polling")) return null
  alert.setAttribute("data-bulk-actions-polling", "")

  const controller = new AbortController()
  const message = alert.querySelector<HTMLElement>("[data-bulk-actions-result-message]")
  const bar = alert.querySelector<HTMLProgressElement>("progress")
  const text = alert.querySelector<HTMLElement>("[data-bulk-actions-progress-text]")

  pollRun(
    url,
    config.pollInterval,
    (status: BulkActionRunStatus) => {
      if (bar) bar.value = status.progress ?? percent(status.processed, status.total)
      if (text) {
        text.textContent = replace(config.strings.progress, {
          processed: formatNumber(status.processed),
          total: formatNumber(status.total),
        })
      }
      if (!status.is_finished) return

      if (message && status.message) message.textContent = status.message
      alert.setAttribute("data-tone", status.result.tone)
      alert.querySelector("[data-bulk-actions-progress]")?.remove()
      alert.dispatchEvent(new CustomEvent("bulk-actions:finished", { bubbles: true, detail: status }))
    },
    controller.signal,
  )

  return controller
}

/** Sets up one `[data-bulk-actions]` bar. */
export function initBar(bar: HTMLElement): void {
  if (bar.hasAttribute("data-bulk-actions-ready")) return
  bar.setAttribute("data-bulk-actions-ready", "")

  const config = parseConfig(bar)
  if (!config) return

  bar
    .querySelectorAll<HTMLElement>("[data-bulk-actions-result][data-run-url]")
    .forEach((alert) => initProgress(alert, config))

  const form = bar.closest("form")
  if (!form) return

  const { name, strings, total } = config
  const count = bar.querySelector<HTMLElement>("[data-bulk-actions-count]")
  const select = bar.querySelector<HTMLSelectElement>('select[name="action"]')
  const allInput = bar.querySelector<HTMLInputElement>("input[data-bulk-actions-all]")
  const filters = bar.querySelector<HTMLFieldSetElement>("fieldset[data-bulk-actions-filters]")
  const banner = bar.querySelector<HTMLElement>("[data-bulk-actions-all-matching]")
  const pageSelected = bar.querySelector<HTMLElement>("[data-bulk-actions-page-selected]")
  const allSelected = bar.querySelector<HTMLElement>("[data-bulk-actions-all-selected]")
  const selectAll = bar.querySelector<HTMLElement>("[data-bulk-actions-select-all]")
  const clear = bar.querySelector<HTMLElement>("[data-bulk-actions-clear]")

  const rows = () =>
    [...form.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')].filter(
      (box) => box.name === `${name}[]` && !box.disabled,
    )
  const isAll = () => allInput?.value === "1"

  const setAll = (value: boolean) => {
    if (allInput) allInput.value = value ? "1" : "0"
    if (filters) filters.disabled = !value
    update()
  }

  const update = () => {
    const boxes = rows()
    const checked = boxes.filter((box) => box.checked).length
    const all = isAll()

    if (count) {
      count.removeAttribute("role")
      count.textContent =
        all && total !== null
          ? replace(strings.selected, { count: formatNumber(total) })
          : checked > 0
            ? replace(strings.selected, { count: formatNumber(checked) })
            : strings.none_selected
    }

    if (!banner) return

    const offer = total !== null && boxes.length > 0 && checked === boxes.length && total > checked
    banner.hidden = !(all || offer)
    if (pageSelected) {
      pageSelected.hidden = all
      pageSelected.textContent = replace(strings.all_on_page_selected, { count: formatNumber(checked) })
    }
    if (allSelected) allSelected.hidden = !all
    if (selectAll) selectAll.hidden = all
    if (clear) clear.hidden = !all
  }

  form.addEventListener("change", (event) => {
    const target = event.target
    if (!(target instanceof HTMLInputElement) || target.type !== "checkbox") return
    // Unchecking a row leaves "all matching".
    if (isAll() && !target.checked) {
      if (allInput) allInput.value = "0"
      if (filters) filters.disabled = true
    }
    // javaabu/forms' select-all checks the rows in a document listener; count after it.
    setTimeout(update, 0)
  })

  selectAll?.addEventListener("click", (event) => {
    event.preventDefault()
    setAll(true)
  })

  clear?.addEventListener("click", (event) => {
    event.preventDefault()
    rows().forEach((box) => (box.checked = false))
    form.querySelectorAll<HTMLInputElement>(`input[type="checkbox"][data-all="${CSS.escape(name)}"]`).forEach((box) => {
      box.checked = false
      box.indeterminate = false
    })
    setAll(false)
  })

  form.addEventListener(
    "submit",
    (event) => {
      const submitter = (event as SubmitEvent).submitter
      // The confirm button of an open dialog submits with that dialog's fields.
      if (submitter?.closest("dialog[data-bulk-actions-dialog]")) return

      const action = select?.value ?? ""
      if (!action) return

      if (!isAll() && rows().every((box) => !box.checked)) {
        event.preventDefault()
        if (count) {
          count.textContent = strings.none_selected
          count.setAttribute("role", "alert")
        }
        return
      }

      if (config.dialogs.includes(action)) {
        event.preventDefault()
        event.stopImmediatePropagation()
        showDialog(bar, action)
        return
      }

      // An action without a dialog submits none of the dialogs' fields.
      bar
        .querySelectorAll<HTMLFieldSetElement>("[data-bulk-actions-fields]")
        .forEach((fields) => (fields.disabled = true))
    },
    true,
  )

  bar.querySelectorAll<HTMLDialogElement>("dialog[data-bulk-actions-dialog]").forEach((dialog) => {
    dialog.addEventListener("close", () => {
      dialog.setAttribute("data-state", "closed")
      const fields = dialog.querySelector<HTMLFieldSetElement>("[data-bulk-actions-fields]")
      if (fields) fields.disabled = true
    })
  })

  // After a validation error, the dialog the user was in opens again with its errors.
  const reopen = bar.querySelector<HTMLDialogElement>("dialog[data-bulk-actions-reopen]")
  if (reopen) showDialog(bar, reopen.getAttribute("data-bulk-actions-dialog") ?? "")

  update()
}

export function init(root: ParentNode = document): void {
  root.querySelectorAll<HTMLElement>("[data-bulk-actions]").forEach(initBar)
}

function autoInit(): void {
  if (typeof document === "undefined") return

  const start = () => {
    init()
    new MutationObserver(() => init()).observe(document.body, { childList: true, subtree: true })
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start, { once: true })
  else start()
}

autoInit()
