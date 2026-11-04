<?php

namespace Javaabu\BulkActions\Actions;

use Illuminate\Database\Eloquent\Model;

/**
 * Deletes the selected records (soft deletes them when the model uses
 * SoftDeletes), one at a time so model events fire. Each record is checked
 * against the `delete` ability of the model's policy.
 */
class DeleteAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'delete';
    }

    protected function setUp(): void
    {
        $this->label(fn () => __('bulk-actions::strings.actions.delete.label'))
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->confirmationText(fn () => __('bulk-actions::strings.actions.delete.confirm'))
            ->successMessage(fn (Action $action, $result) => trans_choice('bulk-actions::strings.actions.delete.done', $result->succeeded, ['count' => $result->succeeded]))
            ->canEach('delete')
            ->each(fn (Model $record) => $record->delete());
    }
}
