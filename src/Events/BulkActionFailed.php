<?php

namespace Javaabu\BulkActions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\BulkActions\Models\BulkActionRun;
use Throwable;

/**
 * A queued bulk action's job failed. Records that throw in a handler don't
 * fail the run; they're counted as failed.
 */
class BulkActionFailed
{
    use Dispatchable;

    public function __construct(
        public BulkActionRun $run,
        public ?Throwable $exception = null,
    ) {}
}
