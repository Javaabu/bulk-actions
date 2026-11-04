<?php

namespace Javaabu\BulkActions\Actions;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Permanently deletes the selected records, trashed or not. Each record is
 * checked against the `forceDelete` ability of the model's policy. Hidden for
 * models without SoftDeletes.
 */
class ForceDeleteAction extends Action
{
    use InteractsWithTrash;

    public static function getDefaultName(): ?string
    {
        return 'force_delete';
    }

    protected function setUp(): void
    {
        $this->label(fn () => __('bulk-actions::strings.actions.force_delete.label'))
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->confirmationText(fn () => __('bulk-actions::strings.actions.force_delete.confirm'))
            ->successMessage(fn (Action $action, $result) => trans_choice('bulk-actions::strings.actions.force_delete.done', $result->succeeded, ['count' => $result->succeeded]))
            ->canEach('forceDelete')
            ->modifyQueryUsing(fn (Builder $query) => $query->withTrashed())
            ->each(fn (Model $record) => $record->forceDelete());
    }
}
