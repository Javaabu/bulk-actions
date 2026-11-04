<?php

namespace Javaabu\BulkActions\Exceptions;

use LogicException;

class BulkActionsException extends LogicException
{
    public static function actionNeedsName(string $class): self
    {
        return new self("Bulk action [{$class}] needs a name: {$class}::make('name').");
    }

    public static function actionHasNoHandler(string $name): self
    {
        return new self("Bulk action [{$name}] has nothing to do. Give it ->handle(fn (Collection \$records) => …) or ->each(fn (Model \$record) => …).");
    }

    public static function duplicateAction(string $name): self
    {
        return new self("Two bulk actions are named [{$name}]. Action names must be unique in a BulkActions.");
    }

    public static function missingModel(string $class): self
    {
        return new self("[{$class}] doesn't know which model it acts on. Set ->model(Article::class), ->query(…) or the \$model property.");
    }

    public static function missingUrl(string $class): self
    {
        return new self("[{$class}] has no URL to submit to. Register it with Route::bulkActions('articles', {$class}::class), or set ->url(…).");
    }

    public static function cannotQueueInline(string $action): self
    {
        return new self("Bulk action [{$action}] needs to run on the queue, but its BulkActions is inline. Queued runs rebuild the actions in the job, so define them in a class that extends BulkActions, or use ->queue(false).");
    }
}
