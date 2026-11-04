import type { BulkActionRunStatus } from "@javaabu/bulk-actions"

export function runStatus(overrides: Partial<BulkActionRunStatus> = {}): BulkActionRunStatus {
  return {
    id: "run-1",
    name: "articles",
    action: "delete",
    status: "processing",
    status_label: "Running",
    progress: 25,
    total: 1000,
    processed: 250,
    succeeded: 250,
    skipped: 0,
    failed: 0,
    message: "ފަހުން",
    is_finished: false,
    is_cancellable: true,
    cancel_url: "/bulk-actions/runs/run-1/cancel",
    result: {
      action: "delete",
      status: "queued",
      tone: "info",
      total: 1000,
      succeeded: 250,
      skipped: 0,
      failed: 0,
      message: "ފަހުން",
      run: "run-1",
      run_url: "/bulk-actions/runs/run-1",
    },
    ...overrides,
  }
}
