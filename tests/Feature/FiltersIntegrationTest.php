<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Support\FiltersAdapter;
use Javaabu\BulkActions\Tests\Fixtures\Filters\ArticleFilters;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Filters\Filters;
use Javaabu\Filters\FiltersServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

/**
 * "All matching" with javaabu/filters, which is optional: skipped without it.
 */
class FiltersIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Filters::class)) {
            $this->markTestSkipped('javaabu/filters is not installed.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), FiltersServiceProvider::class];
    }

    protected function bulkActions(mixed $filters): BulkActions
    {
        return BulkActions::make([
            Action::make('publish')->handle(fn (Collection $records) => $records->each->publish()),
        ])->model(Article::class)->filters($filters)->user($this->admin());
    }

    protected function seedArticles(): void
    {
        Article::query()->create(['title' => 'މާލެ ރިޕޯޓް', 'status' => 'draft']);
        Article::query()->create(['title' => 'މާލެ ޚަބަރު', 'status' => 'draft']);
        Article::query()->create(['title' => 'މާލެ ރިވިއު', 'status' => 'published']);
        Article::query()->create(['title' => 'އައްޑު ރިޕޯޓް', 'status' => 'draft']);
    }

    #[Test]
    public function it_runs_on_all_records_matching_a_filters_class(): void
    {
        $this->seedArticles();

        request()->merge(['action' => 'publish', 'all' => '1', 'filters' => ['search' => 'މާލެ', 'status' => ['draft']]]);

        $result = $this->bulkActions(ArticleFilters::class)->execute();

        $this->assertEquals(2, $result->total);
        $this->assertEquals(3, Article::query()->where('status', 'published')->count());
        $this->assertDatabaseHas('articles', ['title' => 'އައްޑު ރިޕޯޓް', 'status' => 'draft']);
    }

    #[Test]
    public function it_runs_on_all_records_matching_a_filters_instance(): void
    {
        $this->seedArticles();

        request()->merge(['action' => 'publish', 'all' => '1', 'filters' => ['search' => 'ރިޕޯޓް']]);

        $result = $this->bulkActions(ArticleFilters::make())->execute();

        $this->assertEquals(2, $result->succeeded);
    }

    #[Test]
    public function it_ignores_values_the_filters_do_not_allow(): void
    {
        $this->seedArticles();

        request()->merge(['action' => 'publish', 'all' => '1', 'filters' => ['status' => ['archived'], 'title' => 'x']]);

        $this->assertEquals(4, $this->bulkActions(ArticleFilters::class)->execute()->total);
    }

    #[Test]
    public function it_sends_the_applied_filters_back_for_all_matching(): void
    {
        $this->app['request']->query->replace(['search' => 'މާލެ', 'status' => ['draft', 'nope'], 'orderby' => 'title', 'page' => '3']);

        $parameters = $this->bulkActions(ArticleFilters::class)->getFilterParameters();

        $this->assertEquals(['search' => 'މާލެ', 'status' => ['draft']], $parameters);
        $this->assertEquals($parameters, FiltersAdapter::parameters(ArticleFilters::make()));
    }

    #[Test]
    public function it_rejects_filters_it_cannot_apply(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FiltersAdapter::apply(new stdClass, Article::query(), []);
    }
}
