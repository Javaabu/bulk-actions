<?php

namespace Javaabu\BulkActions\Tests\Fixtures\BulkActions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;

/**
 * One class configured at runtime, like post-types' PostBulkActions::for($post_type):
 * the context says which status it lists, and fromContext() rebuilds it.
 */
class StatusBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    protected ?string $status = null;

    public static function for(string $status): static
    {
        $bulk_actions = static::make();
        $bulk_actions->status = $status;

        return $bulk_actions;
    }

    public function context(): array
    {
        return ['status' => $this->status];
    }

    public static function fromContext(array $context): static
    {
        return static::for($context['status']);
    }

    public function newQuery(): Builder
    {
        return Article::query()->where('status', $this->status);
    }

    protected function actions(): array
    {
        return [
            Action::make('archive')
                ->label(fn () => "{$this->status} ފައިލުކުރޭ")
                ->queue()
                ->handle(fn (Collection $records) => $records->each->update(['status' => "archived_{$this->status}"])),
        ];
    }
}
