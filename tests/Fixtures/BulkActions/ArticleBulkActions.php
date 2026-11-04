<?php

namespace Javaabu\BulkActions\Tests\Fixtures\BulkActions;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\Actions\ForceDeleteAction;
use Javaabu\BulkActions\Actions\RestoreAction;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;

class ArticleBulkActions extends BulkActions
{
    protected ?string $model = Article::class;

    /** Called after each record of publish_one_by_one, for tests */
    public static ?Closure $after_chunk = null;

    protected function setUp(): void
    {
        $this->filters(function (Builder $query, array $filters) {
            return $query
                ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('title', 'like', "%{$search}%"))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
        });
    }

    protected function actions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
            ForceDeleteAction::make(),
            Action::make('publish')
                ->label('ޝާއިޢުކުރޭ')
                ->icon('check')
                ->can('publish', Article::class)
                ->canEach('update')
                ->requiresConfirmation()
                ->successMessage(':count ލިޔުން ޝާއިޢުކުރެވިއްޖެ')
                ->handle(fn (Collection $records) => $records->each->publish()),
            Action::make('publish_one_by_one')
                ->chunkSize(1)
                ->each(function (Article $article) {
                    $article->publish();

                    if (static::$after_chunk) {
                        (static::$after_chunk)();
                    }
                }),
            Action::make('change_status')
                ->label('ހާލަތު ބަދަލުކުރޭ')
                ->canEach('update')
                ->form([
                    Select::make('status')->options(['draft' => 'ޑްރާފްޓް', 'published' => 'ޝާއިޢު'])->required(),
                    TextInput::make('note')->thaana(),
                ])
                ->each(fn (Article $article, array $data) => $article->update(['status' => $data['status']])),
        ];
    }
}
