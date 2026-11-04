<?php

namespace Javaabu\BulkActions\Execution;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Throwable;

/**
 * Runs an action on the selected records, a chunk at a time, skipping the
 * records the user may not act on. Used during the request and by the job.
 */
class Runner
{
    /**
     * @param  array<string, mixed>  $data  the action form's validated values
     */
    public function __construct(
        protected BulkActions $bulk_actions,
        protected Action $action,
        protected Selection $selection,
        protected array $data = [],
        protected ?Authorizable $user = null,
    ) {}

    /**
     * The selected records: the BulkActions' query, changed by the action,
     * narrowed to the checked ids or filtered like the listing.
     */
    public function query(): Builder
    {
        $query = $this->action->modifyQuery($this->bulk_actions->newQuery());

        if ($this->selection->all) {
            return $this->bulk_actions->applyFilters($query, $this->selection->filters);
        }

        return $query->whereKey($this->selection->ids);
    }

    public function count(): int
    {
        return $this->query()->toBase()->getCountForPagination();
    }

    /**
     * Run the action. After each chunk, `$after_chunk` gets the result so far;
     * return false from it to stop (the result is then cancelled).
     *
     * @param  (Closure(ActionResult): (bool|null))|null  $after_chunk
     */
    public function run(?int $total = null, ?Closure $after_chunk = null): ActionResult
    {
        $result = new ActionResult(
            action: $this->action->getName(),
            total: $total ?? $this->count(),
        );

        $query = $this->query()->reorder();

        $completed = $query->chunkById($this->action->getChunkSize(), function (EloquentCollection $records) use ($result, $after_chunk) {
            $this->processChunk($records, $result);

            if ($after_chunk === null) {
                return true;
            }

            return $after_chunk($result) !== false;
        });

        if (! $completed) {
            $result->status = ActionResult::CANCELLED;
        }

        $result->message = $this->message($result);

        return $result;
    }

    /**
     * @param  EloquentCollection<int, Model>  $records
     */
    protected function processChunk(EloquentCollection $records, ActionResult $result): void
    {
        [$allowed, $denied] = $records->partition(fn (Model $record) => $this->action->isAuthorizedFor($this->user, $record));

        $result->skipped += $denied->count();

        if ($allowed->isEmpty()) {
            return;
        }

        if ($this->action->processesEachRecord()) {
            foreach ($allowed as $record) {
                $this->processRecords($allowed->make([$record]), $result);
            }

            return;
        }

        $this->processRecords($allowed->values(), $result);
    }

    /**
     * @param  EloquentCollection<int, Model>  $records
     */
    protected function processRecords(EloquentCollection $records, ActionResult $result): void
    {
        $count = $records->count();

        try {
            $succeeded = min($count, max(0, $this->action->process($records, $this->data, $this->user)));
        } catch (Throwable $exception) {
            report($exception);

            $result->failed += $count;

            return;
        }

        $result->succeeded += $succeeded;
        $result->failed += $count - $succeeded;
    }

    protected function message(ActionResult $result): string
    {
        $parts = [$this->action->getSuccessMessage($result)];

        if ($result->skipped > 0) {
            $parts[] = trans_choice('bulk-actions::strings.skipped', $result->skipped, ['count' => $result->skipped]);
        }

        if ($result->failed > 0) {
            $parts[] = trans_choice('bulk-actions::strings.failed', $result->failed, ['count' => $result->failed]);
        }

        if ($result->status === ActionResult::CANCELLED) {
            $parts[] = __('bulk-actions::strings.cancelled');
        }

        return implode(' ', $parts);
    }
}
