<?php

namespace Javaabu\BulkActions\Execution;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * What a bulk action did: how many records it was run on, and how many
 * succeeded, were skipped (the user may not act on them) or failed.
 *
 * @implements Arrayable<string, mixed>
 */
class ActionResult implements Arrayable, JsonSerializable
{
    public const string COMPLETED = 'completed';

    public const string QUEUED = 'queued';

    public const string CANCELLED = 'cancelled';

    public const string FAILED = 'failed';

    public function __construct(
        public string $action,
        public string $status = self::COMPLETED,
        public int $total = 0,
        public int $succeeded = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public string $message = '',
        public ?string $run = null,
        public ?string $run_url = null,
    ) {}

    public function processed(): int
    {
        return $this->succeeded + $this->skipped + $this->failed;
    }

    public function isQueued(): bool
    {
        return $this->status === self::QUEUED;
    }

    /**
     * The tone of the message: success when every record succeeded, warning
     * when some were skipped or failed, danger when none succeeded.
     */
    public function tone(): string
    {
        if ($this->status === self::QUEUED) {
            return 'info';
        }

        if ($this->status === self::FAILED) {
            return 'danger';
        }

        if ($this->skipped === 0 && $this->failed === 0) {
            return 'success';
        }

        if ($this->succeeded === 0) {
            return 'danger';
        }

        return 'warning';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            action: (string) ($data['action'] ?? ''),
            status: (string) ($data['status'] ?? self::COMPLETED),
            total: (int) ($data['total'] ?? 0),
            succeeded: (int) ($data['succeeded'] ?? 0),
            skipped: (int) ($data['skipped'] ?? 0),
            failed: (int) ($data['failed'] ?? 0),
            message: (string) ($data['message'] ?? ''),
            run: $data['run'] ?? null,
            run_url: $data['run_url'] ?? null,
        );
    }

    /**
     * @return array{
     *     action: string,
     *     status: string,
     *     tone: string,
     *     total: int,
     *     succeeded: int,
     *     skipped: int,
     *     failed: int,
     *     message: string,
     *     run: string|null,
     *     run_url: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'status' => $this->status,
            'tone' => $this->tone(),
            'total' => $this->total,
            'succeeded' => $this->succeeded,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'message' => $this->message,
            'run' => $this->run,
            'run_url' => $this->run_url,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
