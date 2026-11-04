import type { BulkActionRunStatus } from "./types"

/** Laravel-style replacements: `:count selected` with `{ count: 3 }`. Longer keys first. */
export function replace(line: string, replacements: Record<string, string | number> = {}): string {
  return Object.keys(replacements)
    .sort((a, b) => b.length - a.length)
    .reduce((out, key) => out.split(`:${key}`).join(String(replacements[key])), line)
}

/** Formats a count for the current document language. */
export function formatNumber(value: number): string {
  try {
    return new Intl.NumberFormat(document.documentElement.lang || undefined).format(value)
  } catch {
    return String(value)
  }
}

/**
 * Nested query parameters as field names and values, like PHP's `QueryFields::flatten()`:
 * `{ status: ["draft"] }` under `filters` → `filters[status][0]=draft`.
 */
export function flattenQuery(parameters: unknown, prefix: string): Array<[string, string]> {
  if (parameters === null || parameters === undefined) return []

  if (typeof parameters !== "object") {
    return [[prefix, typeof parameters === "boolean" ? (parameters ? "1" : "0") : String(parameters)]]
  }

  return Object.entries(parameters as Record<string, unknown>).flatMap(([key, value]) =>
    flattenQuery(value, `${prefix}[${key}]`),
  )
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined
  const match = document.cookie.split("; ").find((cookie) => cookie.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : undefined
}

/** Headers for a JSON request to Laravel, with the CSRF token from the meta tag or XSRF cookie. */
export function jsonHeaders(): Record<string, string> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  }

  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  const xsrf = readCookie("XSRF-TOKEN")

  if (meta) headers["X-CSRF-TOKEN"] = meta
  else if (xsrf) headers["X-XSRF-TOKEN"] = xsrf

  return headers
}

/** The status of a queued run. */
export async function fetchRun(url: string, signal?: AbortSignal): Promise<BulkActionRunStatus> {
  const response = await fetch(url, { headers: jsonHeaders(), credentials: "same-origin", signal })
  if (!response.ok) throw new Error(`Bulk action status failed with ${response.status}`)
  const body = (await response.json()) as { data: BulkActionRunStatus }
  return body.data
}

/** Cancels a queued run. */
export async function cancelRun(url: string): Promise<BulkActionRunStatus> {
  const response = await fetch(url, { method: "POST", headers: jsonHeaders(), credentials: "same-origin" })
  if (!response.ok) throw new Error(`Cancelling the bulk action failed with ${response.status}`)
  const body = (await response.json()) as { data: BulkActionRunStatus }
  return body.data
}

/**
 * Polls a run every `interval` ms until it finishes or `signal` aborts. Calls
 * `onUpdate` with each status. Network errors are retried on the next tick.
 */
export function pollRun(
  url: string,
  interval: number,
  onUpdate: (status: BulkActionRunStatus) => void,
  signal: AbortSignal,
): void {
  const tick = async () => {
    if (signal.aborted) return
    try {
      const status = await fetchRun(url, signal)
      if (signal.aborted) return
      onUpdate(status)
      if (status.is_finished) return
    } catch {
      if (signal.aborted) return
    }
    setTimeout(tick, interval)
  }

  void tick()
}

/** The percent done of a run, 0–100. */
export function percent(processed: number, total: number): number {
  if (total <= 0) return 0
  return Math.min(100, Math.floor((processed / total) * 100))
}
