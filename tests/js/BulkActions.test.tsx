import { act, fireEvent, render, screen, waitFor } from "@testing-library/react"
import userEvent from "@testing-library/user-event"
import { afterEach, describe, expect, it, vi } from "vitest"

import { FormsProvider, TableCell, TableRow, type FormsRouter, type SubmitRequest } from "@javaabu/forms"
import { BulkActionsResult, BulkActionsTable, type BulkActionsSchema } from "@javaabu/bulk-actions"

import fixture from "./fixtures/bulk-actions.json"
import { runStatus } from "./helpers"

const schema = fixture as unknown as BulkActionsSchema

function clone(overrides: Partial<BulkActionsSchema> = {}): BulkActionsSchema {
  return { ...structuredClone(schema), ...overrides }
}

const rows = [
  { id: 1, title: "ލިޔުން 1" },
  { id: 2, title: "ލިޔުން 2" },
]

function setUp(bulkActions = clone()) {
  const submits: Array<{ url: string; values: Record<string, unknown> }> = []
  const router: FormsRouter = {
    name: "test",
    submit(request: SubmitRequest, callbacks) {
      const values: Record<string, unknown> = {}
      request.data.forEach((value, key) => {
        const existing = values[key]
        values[key] = existing === undefined ? value : ([] as unknown[]).concat(existing, value)
      })
      submits.push({ url: request.url, values })
      callbacks.onStart()
      callbacks.onSuccess()
      callbacks.onFinish()
    },
  }

  // jsdom has no <dialog> methods.
  HTMLDialogElement.prototype.showModal = function () {
    this.setAttribute("open", "")
  }
  HTMLDialogElement.prototype.close = function () {
    this.removeAttribute("open")
  }

  const utils = render(
    <FormsProvider router={router}>
      <BulkActionsTable bulkActions={bulkActions}>
        {rows.map((row) => (
          <TableRow key={row.id} model={row}>
            <TableCell name="title" />
          </TableRow>
        ))}
      </BulkActionsTable>
    </FormsProvider>,
  )

  const rowBox = (id: number) =>
    utils.container.querySelector<HTMLInputElement>(`input[name="articles[]"][value="${id}"]`)!

  const choose = (action: string) => {
    fireEvent.change(utils.container.querySelector<HTMLSelectElement>('select[name="action"]')!, {
      target: { value: action },
    })
  }

  const apply = () => fireEvent.click(screen.getByRole("button", { name: "Apply" }))

  return { ...utils, submits, rowBox, choose, apply }
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.useRealTimers()
})

describe("BulkActionsTable", () => {
  it("renders the actions in the table's bulk form", () => {
    const { container } = setUp()

    const form = container.querySelector("form")!
    expect(form).toHaveAttribute("action", "http://localhost/articles/bulk")
    expect(container.querySelector('select[name="action"]')).not.toBeNull()
    expect(screen.getByRole("option", { name: "ޝާއިޢުކުރޭ" })).toBeInTheDocument()
    expect(screen.getByText("Select records to apply an action")).toBeInTheDocument()
  })

  it("leaves out the bar when there are no actions", () => {
    const { container } = setUp(clone({ actions: [] }))
    expect(container.querySelector('select[name="action"]')).toBeNull()
  })

  it("asks for an action and a selection", async () => {
    const { apply, choose, submits } = setUp()

    apply()
    expect(await screen.findByRole("alert")).toHaveTextContent("Choose an action")

    choose("restore")
    apply()
    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("Select records to apply an action"))
    expect(submits).toHaveLength(0)
  })

  it("counts the selection and submits actions without a dialog straight away", async () => {
    const { rowBox, choose, apply, submits } = setUp()

    fireEvent.click(rowBox(1))
    await waitFor(() => expect(screen.getByText("1 selected")).toBeInTheDocument())

    choose("restore")
    apply()

    await waitFor(() => expect(submits).toHaveLength(1))
    expect(submits[0]!.url).toBe("http://localhost/articles/bulk")
    expect(submits[0]!.values).toMatchObject({ action: "restore", "articles[]": "1" })
    expect(submits[0]!.values.all).toBeUndefined()
  })

  it("confirms before running", async () => {
    const { rowBox, choose, apply, submits } = setUp()

    fireEvent.click(rowBox(1))
    fireEvent.click(rowBox(2))
    choose("delete")
    apply()

    const dialog = await screen.findByRole("dialog")
    expect(dialog).toHaveTextContent("Are you sure you want to delete the selected records?")
    expect(submits).toHaveLength(0)

    fireEvent.click(screen.getAllByRole("button", { name: "Delete" }).at(-1)!)

    await waitFor(() => expect(submits).toHaveLength(1))
    expect(submits[0]!.values).toMatchObject({ action: "delete", "articles[]": ["1", "2"] })
    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull())
  })

  it("cancels the dialog", async () => {
    const { rowBox, choose, apply, submits } = setUp()

    fireEvent.click(rowBox(1))
    choose("delete")
    apply()

    await screen.findByRole("dialog")
    fireEvent.click(screen.getByRole("button", { name: "Cancel" }))

    await waitFor(() => expect(screen.queryByRole("dialog")).toBeNull())
    expect(submits).toHaveLength(0)
  })

  it("asks for the action's form and submits its values", async () => {
    const user = userEvent.setup()
    const { rowBox, choose, apply, submits, container } = setUp()

    fireEvent.click(rowBox(2))
    choose("change_status")
    apply()

    await screen.findByRole("dialog")
    const status = container.ownerDocument.querySelector<HTMLSelectElement>('dialog select[name="status"]')!
    fireEvent.change(status, { target: { value: "published" } })
    await user.type(container.ownerDocument.querySelector<HTMLInputElement>('dialog input[name="note"]')!, "ok")

    fireEvent.click(screen.getByRole("button", { name: "ހާލަތު ބަދަލުކުރޭ" }))

    await waitFor(() => expect(submits).toHaveLength(1))
    expect(submits[0]!.values).toMatchObject({ action: "change_status", "articles[]": "2", status: "published" })
  })

  it("selects all matching records and sends the filters", async () => {
    const { rowBox, choose, apply, submits } = setUp()

    fireEvent.click(rowBox(1))
    fireEvent.click(rowBox(2))

    fireEvent.click(await screen.findByRole("button", { name: "Select all 3" }))
    expect(screen.getByText("All 3 records are selected.")).toBeInTheDocument()
    expect(screen.getByText("3 selected")).toBeInTheDocument()

    choose("restore")
    apply()

    await waitFor(() => expect(submits).toHaveLength(1))
    expect(submits[0]!.values).toMatchObject({ action: "restore", all: "1", "filters[search]": "މާލެ" })
    expect(submits[0]!.values["articles[]"]).toBeUndefined()
  })

  it("clears all matching", async () => {
    const { rowBox } = setUp()

    fireEvent.click(rowBox(1))
    fireEvent.click(rowBox(2))
    fireEvent.click(await screen.findByRole("button", { name: "Select all 3" }))
    fireEvent.click(screen.getByRole("button", { name: "Clear selection" }))

    expect(screen.queryByText("All 3 records are selected.")).toBeNull()
  })
})

describe("BulkActionsResult", () => {
  it("shows the last result and dismisses it", () => {
    const bulkActions = clone({
      result: {
        ...runStatus().result,
        status: "completed",
        tone: "success",
        message: "2 ފޮހެލެވިއްޖެ",
        run: null,
        run_url: null,
      },
    })

    render(<BulkActionsResult schema={bulkActions} />)

    expect(screen.getByText("2 ފޮހެލެވިއްޖެ")).toBeInTheDocument()
    fireEvent.click(screen.getByRole("button", { name: "Dismiss" }))
    expect(screen.queryByText("2 ފޮހެލެވިއްޖެ")).toBeNull()
  })

  it("renders nothing without a result", () => {
    const { container } = render(<BulkActionsResult schema={clone()} />)
    expect(container).toBeEmptyDOMElement()
  })

  it("follows a queued run, can stop it, and reports when it finishes", async () => {
    vi.useFakeTimers()
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ data: runStatus() }) } as Response)
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ data: runStatus() }) } as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          data: runStatus({
            is_finished: true,
            status: "completed",
            message: "ނިމިއްޖެ",
            result: { ...runStatus().result, status: "completed", tone: "success", message: "ނިމިއްޖެ" },
          }),
        }),
      } as Response)
    const onRunFinished = vi.fn()

    render(
      <BulkActionsResult
        schema={clone({ result: runStatus().result, pollInterval: 1000 })}
        onRunFinished={onRunFinished}
      />,
    )

    expect(screen.getByText("ފަހުން")).toBeInTheDocument()
    await act(() => vi.advanceTimersByTimeAsync(1))
    expect(screen.getByText("250 of 1,000 done")).toBeInTheDocument()

    fireEvent.click(screen.getByRole("button", { name: "Stop" }))
    expect(fetch.mock.calls[1]![0]).toBe("/bulk-actions/runs/run-1/cancel")

    await act(() => vi.advanceTimersByTimeAsync(1000))
    expect(screen.getByText("ނިމިއްޖެ")).toBeInTheDocument()
    expect(onRunFinished).toHaveBeenCalledOnce()
  })
})
