<?php

namespace Javaabu\BulkActions\Tests\Fixtures\Policies;

use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\Fixtures\Models\User;

class ArticlePolicy
{
    public function update(User $user, Article $article): bool
    {
        return $user->is_admin || $article->user_id === $user->id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->update($user, $article);
    }

    public function restore(User $user, Article $article): bool
    {
        return $this->update($user, $article);
    }

    public function forceDelete(User $user, Article $article): bool
    {
        return $user->is_admin;
    }

    public function publish(User $user): bool
    {
        return $user->is_admin;
    }
}
