<?php

namespace Javaabu\BulkActions\Actions;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Restores the selected soft-deleted records. Only trashed records are
 * selected, and each is checked against the `restore` ability of the model's
 * policy. Hidden for models without SoftDeletes.
 */
class RestoreAction extends Action
{
    use InteractsWithTrash;

    public static function getDefaultName(): ?string
    {
        return 'restore';
    }

    protected function setUp(): void
    {
        $this->label(fn () => __('bulk-actions::strings.actions.restore.label'))
            ->icon('undo')
            ->color('success')
            ->successMessage(fn (Action $action, $result) => trans_choice('bulk-actions::strings.actions.restore.done', $result->succeeded, ['count' => $result->succeeded]))
            ->canEach('restore')
            ->modifyQueryUsing(fn (Builder $query) => $query->onlyTrashed())
            ->each(fn (Model $record) => $record->restore());
    }
}
