import { afterEach, beforeEach, describe, expect, it, vi } from "vitest"

import { init, initProgress } from "../../resources/js/blade/bulk-actions"
import html from "./fixtures/table.html?raw"
import { runStatus } from "./helpers"

function setUp(markup = html) {
  document.body.innerHTML = markup
  // jsdom has no <dialog> methods.
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute("open", "")
  }
  HTMLDialogElement.prototype.close = function () {
    this.removeAttribute("open")
    this.dispatchEvent(new Event("close"))
  }
  init()

  const form = document.querySelector("form")!
  const bar = document.querySelector<HTMLElement>("[data-bulk-actions]")!
  const rows = [...document.querySelectorAll<HTMLInputElement>('input[name="articles[]"]')]
  const select = bar.querySelector<HTMLSelectElement>('select[name="action"]')!
  const count = bar.querySelector<HTMLElement>("[data-bulk-actions-count]")!
  const banner = bar.querySelector<HTMLElement>("[data-bulk-actions-all-matching]")!
  const all = bar.querySelector<HTMLInputElement>("input[data-bulk-actions-all]")!
  const filters = bar.querySelector<HTMLFieldSetElement>("[data-bulk-actions-filters]")!
  const dialog = (name: string) => bar.querySelector<HTMLDialogElement>(`dialog[data-bulk-actions-dialog="${name}"]`)!

  const check = async (box: HTMLInputElement, checked = true) => {
    box.checked = checked
    box.dispatchEvent(new Event("change", { bubbles: true }))
    await vi.advanceTimersByTimeAsync(1)
  }

  const submit = (submitter?: HTMLElement) => {
    const event = new SubmitEvent("submit", { bubbles: true, cancelable: true, submitter: submitter ?? null })
    form.dispatchEvent(event)
    return event
  }

  return { form, bar, rows, select, count, banner, all, filters, dialog, check, submit }
}

beforeEach(() => vi.useFakeTimers())

afterEach(() => {
  vi.useRealTimers()
  vi.restoreAllMocks()
  document.body.innerHTML = ""
})

describe("Blade script", () => {
  it("counts the selected rows", async () => {
    const { rows, count, check } = setUp()

    expect(count.textContent).toBe("Select records to apply an action")

    await check(rows[0]!)
    expect(count.textContent).toBe("1 selected")
  })

  it("offers all matching once the whole page is selected, and sends the filters", async () => {
    const { rows, banner, all, filters, count, check, bar } = setUp()

    expect(banner.hidden).toBe(true)
    expect(filters.disabled).toBe(true)

    await check(rows[0]!)
    await check(rows[1]!)
    expect(banner.hidden).toBe(false)
    expect(banner.textContent).toContain("All 2 records on this page are selected.")

    bar.querySelector<HTMLElement>("[data-bulk-actions-select-all]")!.click()
    expect(all.value).toBe("1")
    expect(filters.disabled).toBe(false)
    expect(count.textContent).toBe("3 selected")
    expect(bar.querySelector<HTMLElement>("[data-bulk-actions-all-selected]")!.hidden).toBe(false)
    expect(filters.querySelector<HTMLInputElement>('input[name="filters[search]"]')!.value).toBe("މާލެ")

    // Unchecking a row leaves "all matching".
    await check(rows[0]!, false)
    expect(all.value).toBe("0")
    expect(filters.disabled).toBe(true)
  })

  it("clears the selection", async () => {
    const { rows, all, bar, check } = setUp()

    await check(rows[0]!)
    await check(rows[1]!)
    bar.querySelector<HTMLElement>("[data-bulk-actions-select-all]")!.click()
    bar.querySelector<HTMLElement>("[data-bulk-actions-clear]")!.click()

    expect(all.value).toBe("0")
    expect(rows.every((row) => !row.checked)).toBe(true)
  })

  it("stops a submit with nothing selected", () => {
    const { select, submit, count } = setUp()

    select.value = "restore"
    const event = submit()

    expect(event.defaultPrevented).toBe(true)
    expect(count).toHaveAttribute("role", "alert")
  })

  it("submits actions without a dialog straight away, without any dialog fields", async () => {
    const { rows, select, submit, check, bar } = setUp()

    await check(rows[0]!)
    select.value = "restore"

    expect(submit().defaultPrevented).toBe(false)
    expect([...bar.querySelectorAll<HTMLFieldSetElement>("[data-bulk-actions-fields]")].every((f) => f.disabled)).toBe(
      true,
    )
  })

  it("opens the dialog of actions with a confirmation or a form, and submits its fields", async () => {
    const { rows, select, submit, check, dialog } = setUp()

    await check(rows[0]!)
    select.value = "change_status"

    expect(submit().defaultPrevented).toBe(true)

    const changeStatus = dialog("change_status")
    expect(changeStatus).toHaveAttribute("open")
    expect(changeStatus.querySelector("fieldset")!.disabled).toBe(false)
    expect(dialog("delete").querySelector("fieldset")!.disabled).toBe(true)
    expect(changeStatus.querySelector('select[name="status"]')).not.toBeNull()

    // The dialog's confirm button submits.
    const confirm = changeStatus.querySelector<HTMLElement>("[data-bulk-actions-confirm]")!
    expect(submit(confirm).defaultPrevented).toBe(false)

    // Closing it disables its fields again.
    changeStatus.close()
    expect(changeStatus.querySelector("fieldset")!.disabled).toBe(true)
    expect(changeStatus).toHaveAttribute("data-state", "closed")
  })

  it("does nothing without an action, leaving it to the required select", () => {
    const { submit } = setUp()
    expect(submit().defaultPrevented).toBe(false)
  })

  it("reopens the dialog after a validation error", () => {
    const markup = html.replace(
      'data-bulk-actions-dialog="change_status"',
      'data-bulk-actions-dialog="change_status" data-bulk-actions-reopen="1"',
    )
    const { dialog } = setUp(markup)

    expect(dialog("change_status")).toHaveAttribute("open")
  })

  it("only sets up a bar once", () => {
    const { bar } = setUp()
    init()
    expect(bar).toHaveAttribute("data-bulk-actions-ready")
  })

  it("follows a queued run until it finishes", async () => {
    document.body.innerHTML = `
      <div data-slot="callout" data-tone="info" data-bulk-actions-result data-run-url="/runs/1">
        <span data-bulk-actions-result-message>ފަހުން</span>
        <span data-bulk-actions-progress><progress max="100"></progress><span data-bulk-actions-progress-text></span></span>
      </div>`
    const alert = document.querySelector<HTMLElement>("[data-bulk-actions-result]")!
    const finished = vi.fn()
    alert.addEventListener("bulk-actions:finished", finished)

    vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ data: runStatus() }) } as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          data: runStatus({
            is_finished: true,
            status: "completed",
            progress: 100,
            processed: 1000,
            message: "1000 ފޮހެލެވިއްޖެ",
            result: { ...runStatus().result, status: "completed", tone: "success" },
          }),
        }),
      } as Response)

    const strings = { progress: ":processed of :total done" } as never
    initProgress(alert, { pollInterval: 1000, strings })
    expect(initProgress(alert, { pollInterval: 1000, strings })).toBeNull()

    await vi.advanceTimersByTimeAsync(1)
    expect(alert.querySelector("progress")!.value).toBe(25)
    expect(alert.querySelector("[data-bulk-actions-progress-text]")!.textContent).toBe("250 of 1,000 done")

    await vi.advanceTimersByTimeAsync(1000)
    expect(alert.querySelector("[data-bulk-actions-result-message]")!.textContent).toBe("1000 ފޮހެލެވިއްޖެ")
    expect(alert).toHaveAttribute("data-tone", "success")
    expect(alert.querySelector("[data-bulk-actions-progress]")).toBeNull()
    expect(finished).toHaveBeenCalledOnce()
  })
})
