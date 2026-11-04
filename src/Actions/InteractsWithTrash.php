<?php

namespace Javaabu\BulkActions\Actions;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hides an action unless the model uses SoftDeletes.
 */
trait InteractsWithTrash
{
    public function isVisible(): bool
    {
        if (! $this->modelSoftDeletes()) {
            return false;
        }

        return parent::isVisible();
    }

    public function isAuthorized(?Authorizable $user): bool
    {
        if (! $this->modelSoftDeletes()) {
            return false;
        }

        return parent::isAuthorized($user);
    }

    protected function modelSoftDeletes(): bool
    {
        $model = $this->bulk_actions?->getModel();

        if ($model === null) {
            return true;
        }

        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
