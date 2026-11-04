<?php

namespace Javaabu\BulkActions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Javaabu\BulkActions\Enums\RunStatuses;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Execution\Selection;
use Throwable;

/**
 * A bulk action running on the queue: who started it, what it runs on, and
 * how far it has got.
 *
 * @property int $id
 * @property string $uuid
 * @property string|null $user_type
 * @property int|string|null $user_id
 * @property class-string $bulk_actions
 * @property string $name
 * @property string $action
 * @property array<string, mixed> $selection
 * @property array<string, mixed>|null $data
 * @property RunStatuses $status
 * @property int $total
 * @property int $succeeded
 * @property int $skipped
 * @property int $failed
 * @property string|null $message
 * @property string|null $error
 * @property string|null $locale
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Model|null $user
 */
class BulkActionRun extends Model
{
    use MassPrunable;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'pending',
        'total' => 0,
        'succeeded' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    protected function casts(): array
    {
        return [
            'selection' => 'array',
            'data' => 'array',
            'status' => RunStatuses::class,
            'total' => 'integer',
            'succeeded' => 'integer',
            'skipped' => 'integer',
            'failed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BulkActionRun $run) {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    public function getTable(): string
    {
        return config('bulk-actions.table', 'bulk_action_runs');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOwnedBy(Builder $query, Model $user): void
    {
        $query->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey());
    }

    /**
     * Finished runs older than config('bulk-actions.prune_after_days'), for `model:prune`.
     */
    public function prunable(): Builder
    {
        $days = (int) config('bulk-actions.prune_after_days', 7);

        return static::query()
            ->whereIn('status', [RunStatuses::COMPLETED, RunStatuses::FAILED, RunStatuses::CANCELLED])
            ->where('updated_at', '<', now()->subDays($days));
    }

    public function isOwnedBy(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->user_type !== $user->getMorphClass()) {
            return false;
        }

        return (string) $this->user_id === (string) $user->getKey();
    }

    public function getSelection(): Selection
    {
        return Selection::fromArray($this->selection ?? []);
    }

    public function processed(): int
    {
        return $this->succeeded + $this->skipped + $this->failed;
    }

    /**
     * Percent done, 0–100, or null before the total is known.
     */
    public function progress(): ?int
    {
        if ($this->status === RunStatuses::COMPLETED) {
            return 100;
        }

        if ($this->total <= 0) {
            return null;
        }

        return (int) min(100, floor($this->processed() / $this->total * 100));
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    public function isCancellable(): bool
    {
        return $this->status->isCancellable();
    }

    /**
     * Whether the run was cancelled, checked against the database so a job
     * sees a cancel made elsewhere.
     */
    public function wasCancelled(): bool
    {
        $status = static::query()->whereKey($this->getKey())->value('status');

        $status = $status instanceof RunStatuses ? $status : RunStatuses::tryFrom((string) $status);

        return $status === RunStatuses::CANCELLED;
    }

    public function markProcessing(int $total): void
    {
        $this->forceFill([
            'status' => RunStatuses::PROCESSING,
            'total' => $total,
            'started_at' => now(),
        ])->save();
    }

    public function recordProgress(ActionResult $result): void
    {
        $this->forceFill([
            'succeeded' => $result->succeeded,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
        ])->save();
    }

    public function markFinished(ActionResult $result): void
    {
        $status = $result->status === ActionResult::CANCELLED
            ? RunStatuses::CANCELLED
            : RunStatuses::COMPLETED;

        $this->forceFill([
            'status' => $status,
            'total' => $result->total,
            'succeeded' => $result->succeeded,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
            'message' => $result->message,
            'finished_at' => now(),
        ])->save();
    }

    public function markFailed(Throwable|string $error): void
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;

        $this->forceFill([
            'status' => RunStatuses::FAILED,
            'error' => Str::limit($message, 1000),
            'message' => __('bulk-actions::strings.run_failed'),
            'finished_at' => now(),
        ])->save();
    }

    /**
     * Cancel the run. The job stops after the chunk it's on.
     */
    public function cancel(): bool
    {
        if (! $this->isCancellable()) {
            return false;
        }

        $this->forceFill([
            'status' => RunStatuses::CANCELLED,
            'message' => __('bulk-actions::strings.cancelled'),
            'finished_at' => now(),
        ])->save();

        return true;
    }

    /**
     * The URL its status is polled from, when Route::bulkActionRuns() is registered.
     */
    public function statusUrl(): ?string
    {
        $route = config('bulk-actions.runs_route', 'bulk-actions.runs') . '.show';

        if (! Route::has($route)) {
            return null;
        }

        return route($route, $this);
    }

    public function cancelUrl(): ?string
    {
        $route = config('bulk-actions.runs_route', 'bulk-actions.runs') . '.cancel';

        if (! Route::has($route)) {
            return null;
        }

        return route($route, $this);
    }

    public function toResult(): ActionResult
    {
        $status = match ($this->status) {
            RunStatuses::COMPLETED => ActionResult::COMPLETED,
            RunStatuses::CANCELLED => ActionResult::CANCELLED,
            RunStatuses::FAILED => ActionResult::FAILED,
            default => ActionResult::QUEUED,
        };

        return new ActionResult(
            action: $this->action,
            status: $status,
            total: $this->total,
            succeeded: $this->succeeded,
            skipped: $this->skipped,
            failed: $this->failed,
            message: (string) $this->message,
            run: $this->uuid,
            run_url: $this->statusUrl(),
        );
    }

    /**
     * The run as the status endpoint returns it.
     *
     * @return array<string, mixed>
     */
    public function toStatusArray(): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'action' => $this->action,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'progress' => $this->progress(),
            'total' => $this->total,
            'processed' => $this->processed(),
            'succeeded' => $this->succeeded,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'message' => $this->message,
            'is_finished' => $this->isFinished(),
            'is_cancellable' => $this->isCancellable(),
            'cancel_url' => $this->isCancellable() ? $this->cancelUrl() : null,
            'result' => $this->toResult()->toArray(),
        ];
    }
}
