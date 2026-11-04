<?php

namespace Javaabu\BulkActions\Events;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Execution\Selection;
use Javaabu\BulkActions\Models\BulkActionRun;

/**
 * A bulk action finished, during the request or on the queue (then `$run` is
 * set). Listen to it to log activity or notify the user.
 */
class BulkActionCompleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $bulk_actions,
        public string $action,
        public ActionResult $result,
        public Selection $selection,
        public array $data,
        public ?Authorizable $user,
        public ?BulkActionRun $run = null,
    ) {}
}
