<?php

namespace Javaabu\BulkActions\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Enums\RunStatuses;
use Javaabu\BulkActions\Events\BulkActionCompleted;
use Javaabu\BulkActions\Events\BulkActionFailed;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Execution\Runner;
use Javaabu\BulkActions\Models\BulkActionRun;
use RuntimeException;
use Throwable;

/**
 * Runs a queued bulk action: rebuilds its BulkActions class for the user who
 * started it, then handles the records a chunk at a time, saving progress
 * and stopping when the run is cancelled. Retrying starts again.
 */
class RunBulkAction implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public bool $failOnTimeout = true;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public BulkActionRun $run)
    {
        $this->timeout = (int) config('bulk-actions.queue.timeout', 3600);
    }

    public function handle(): void
    {
        $previous_locale = app()->getLocale();

        if ($this->run->locale) {
            app()->setLocale($this->run->locale);
        }

        try {
            $this->process();
        } finally {
            app()->setLocale($previous_locale);
        }
    }

    protected function process(): void
    {
        $run = $this->run;

        $run->refresh();

        if ($run->status !== RunStatuses::PENDING) {
            return;
        }

        $user = $run->user;
        $user = $user instanceof Authorizable ? $user : null;

        $bulk_actions = $this->bulkActions($run)->user($user);

        $action = $bulk_actions->getAction($run->action)
            ?? throw new RuntimeException("The bulk action [{$run->action}] no longer exists in [{$run->bulk_actions}].");

        $selection = $run->getSelection();
        $data = $run->data ?? [];

        $runner = new Runner($bulk_actions, $action, $selection, $data, $user);

        $total = $runner->count();

        $run->markProcessing($total);

        $result = $runner->run($total, function (ActionResult $result) use ($run) {
            if ($run->wasCancelled()) {
                return false;
            }

            $run->recordProgress($result);

            return true;
        });

        if ($result->status === ActionResult::CANCELLED) {
            $run->refresh()->recordProgress($result);

            return;
        }

        $run->markFinished($result);

        BulkActionCompleted::dispatch($run->bulk_actions, $action->getName(), $run->toResult(), $selection, $data, $user, $run);
    }

    protected function bulkActions(BulkActionRun $run): BulkActions
    {
        $class = $run->bulk_actions;

        if (! is_a($class, BulkActions::class, true)) {
            throw new RuntimeException("[{$class}] is not a BulkActions class.");
        }

        return $class::make();
    }

    public function failed(?Throwable $exception): void
    {
        $run = $this->run->fresh();

        if (! $run) {
            return;
        }

        if ($run->isFinished()) {
            return;
        }

        $run->markFailed($exception ?? __('bulk-actions::strings.run_failed'));

        BulkActionFailed::dispatch($run, $exception);
    }
}
