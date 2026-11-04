import { afterEach, describe, expect, it, vi } from "vitest"

import { cancelRun, fetchRun, flattenQuery, jsonHeaders, percent, pollRun, replace } from "../../resources/js/support"
import { runStatus } from "./helpers"

function respond(data: unknown, ok = true, status = 200) {
  return { ok, status, json: async () => ({ data }) } as Response
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.useRealTimers()
  document.head.innerHTML = ""
  document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT"
})

describe("support", () => {
  it("replaces placeholders, longest first", () => {
    expect(replace(":count of :countable", { count: 2, countable: "x" })).toBe("2 of x")
    expect(replace("ހުރިހާ :total", { total: "1,000" })).toBe("ހުރިހާ 1,000")
    expect(replace("none")).toBe("none")
  })

  it("flattens nested query parameters like PHP", () => {
    expect(
      flattenQuery({ search: "މާލެ", status: ["draft", "published"], flag: true, off: false, none: null }, "filters"),
    ).toEqual([
      ["filters[search]", "މާލެ"],
      ["filters[status][0]", "draft"],
      ["filters[status][1]", "published"],
      ["filters[flag]", "1"],
      ["filters[off]", "0"],
    ])
    expect(flattenQuery(undefined, "filters")).toEqual([])
  })

  it("sends the CSRF token from the meta tag or the XSRF cookie", () => {
    document.cookie = "XSRF-TOKEN=cookie%3D"
    expect(jsonHeaders()["X-XSRF-TOKEN"]).toBe("cookie=")

    document.head.innerHTML = '<meta name="csrf-token" content="meta-token">'
    expect(jsonHeaders()["X-CSRF-TOKEN"]).toBe("meta-token")
    expect(jsonHeaders()["X-XSRF-TOKEN"]).toBeUndefined()
  })

  it("computes percentages", () => {
    expect(percent(1, 3)).toBe(33)
    expect(percent(5, 0)).toBe(0)
    expect(percent(9, 3)).toBe(100)
  })

  it("fetches and cancels runs", async () => {
    const fetch = vi.spyOn(globalThis, "fetch").mockResolvedValue(respond(runStatus()))

    expect((await fetchRun("/runs/1")).progress).toBe(25)
    expect(fetch.mock.calls[0]![1]!.headers).toMatchObject({ Accept: "application/json" })

    await cancelRun("/runs/1/cancel")
    expect(fetch.mock.calls[1]![1]!.method).toBe("POST")

    fetch.mockResolvedValue(respond(null, false, 403))
    await expect(fetchRun("/runs/1")).rejects.toThrow("403")
    await expect(cancelRun("/runs/1/cancel")).rejects.toThrow("403")
  })

  it("polls until the run finishes, retrying after errors", async () => {
    vi.useFakeTimers()
    const fetch = vi
      .spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(respond(runStatus()))
      .mockRejectedValueOnce(new Error("offline"))
      .mockResolvedValueOnce(respond(runStatus({ is_finished: true, status: "completed" })))
    const updates: string[] = []

    pollRun("/runs/1", 1000, (status) => updates.push(status.status), new AbortController().signal)

    await vi.advanceTimersByTimeAsync(5000)

    expect(updates).toEqual(["processing", "completed"])
    expect(fetch).toHaveBeenCalledTimes(3)
  })

  it("stops polling when aborted", async () => {
    vi.useFakeTimers()
    const fetch = vi.spyOn(globalThis, "fetch").mockResolvedValue(respond(runStatus()))
    const controller = new AbortController()

    pollRun("/runs/1", 1000, () => controller.abort(), controller.signal)
    await vi.advanceTimersByTimeAsync(5000)

    expect(fetch).toHaveBeenCalledTimes(1)
  })
})
