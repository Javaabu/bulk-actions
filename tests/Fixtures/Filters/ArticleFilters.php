<?php

namespace Javaabu\BulkActions\Tests\Fixtures\Filters;

use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\Filters\Filters;
use Javaabu\Filters\SelectFilter;
use Javaabu\Filters\TextFilter;

/**
 * Only loaded when javaabu/filters is installed.
 */
class ArticleFilters extends Filters
{
    protected ?string $model = Article::class;

    protected function filters(): array
    {
        return [
            TextFilter::make('search')->columns(['title']),
            SelectFilter::make('status')->options(['draft' => 'ޑްރާފްޓް', 'published' => 'ޝާއިޢު'])->multiple(),
        ];
    }

    protected function sorts(): array
    {
        return ['title'];
    }
}
