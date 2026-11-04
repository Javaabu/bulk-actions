<?php

namespace Javaabu\BulkActions\Policies;

use Illuminate\Database\Eloquent\Model;
use Javaabu\BulkActions\Models\BulkActionRun;
use Javaabu\Permissions\Policies\ResourcePolicy;

/**
 * Who may follow and cancel queued bulk actions
 *
 * | Ability | Who                                                             |
 * | ------- | --------------------------------------------------------------- |
 * | view    | the user who started it, or `view_others_bulk_action_runs`     |
 * | cancel  | the user who started it, or `cancel_others_bulk_action_runs`   |
 *
 * Override it with `config('permissions.policies')` or `Gate::policy()` in the app.
 */
class BulkActionRunPolicy extends ResourcePolicy
{
    protected string $resource = 'bulk_action_runs';

    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, Model $run): bool
    {
        if ($this->isOwner($user, $run)) {
            return true;
        }

        return $this->allows($user, $this->permission('view_others'));
    }

    public function cancel(Model $user, Model $run): bool
    {
        if ($this->isOwner($user, $run)) {
            return true;
        }

        return $this->allows($user, $this->permission('cancel_others'));
    }

    public function create(Model $user): bool
    {
        return false;
    }

    public function update(Model $user, Model $run): bool
    {
        return false;
    }

    public function delete(Model $user, Model $run): bool
    {
        return false;
    }

    public function restore(Model $user, Model $run): bool
    {
        return false;
    }

    public function forceDelete(Model $user, Model $run): bool
    {
        return false;
    }

    public function viewTrash(Model $user): bool
    {
        return false;
    }

    protected function isOwner(Model $user, Model $model): bool
    {
        return $model instanceof BulkActionRun && $model->isOwnedBy($user);
    }
}
