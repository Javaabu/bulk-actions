<?php

namespace Javaabu\BulkActions\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Javaabu\BulkActions\Models\BulkActionRun;

/**
 * A bulk action was too large to run during the request and was queued.
 */
class BulkActionQueued
{
    use Dispatchable;

    public function __construct(public BulkActionRun $run) {}
}
