<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Manifest\Facades\Manifest;
use PHPUnit\Framework\Attributes\Test;

class BladeViewTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
            $router->bulkActionRuns();
        });
    }

    protected function renderTable(array $data = []): string
    {
        $this->articles(3);

        return Blade::render(<<<'BLADE'
            <x-forms::table model="articles">
                <x-slot:bulk-form :action="$bulkActions->getUrl()">
                    <x-bulk-actions::actions :actions="$bulkActions" :paginator="$articles" />
                </x-slot:bulk-form>
                <x-slot:headers><x-forms::table.heading label="Title" /></x-slot:headers>
                <x-slot:rows>
                    @foreach($articles as $article)
                        <x-forms::table.row :model="$article" name="articles">
                            <x-forms::table.cell name="title" />
                        </x-forms::table.row>
                    @endforeach
                </x-slot:rows>
            </x-forms::table>
            BLADE, ['bulkActions' => ArticleBulkActions::make(), 'articles' => Article::query()->paginate(2), ...$data]);
    }

    #[Test]
    public function it_renders_the_bar_in_the_forms_table_bulk_slot(): void
    {
        $manifest = Manifest::fake();

        $this->actingAs($this->admin());

        $html = $this->renderTable();

        $this->assertStringContainsString('action="' . url('articles/bulk') . '"', $html);
        $this->assertStringContainsString('data-bulk-actions="articles"', $html);
        $this->assertStringContainsString('name="action"', $html);
        $this->assertStringContainsString('ޝާއިޢުކުރޭ', $html);
        $this->assertStringContainsString('name="articles[]"', $html);
        $this->assertStringContainsString('Select all 3', $html);
        $this->assertStringContainsString('All 2 records on this page are selected.', $html);
        $this->assertStringContainsString('name="all" value="0"', $html);
        $this->assertStringContainsString('name="redirect"', $html);
        $this->assertStringContainsString('data-bulk-actions-dialog="delete"', $html);
        $this->assertStringContainsString('data-bulk-actions-dialog="change_status"', $html);
        $this->assertStringNotContainsString('data-bulk-actions-dialog="restore"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertMatchesRegularExpression('/<fieldset[^>]*data-bulk-actions-fields[^>]*disabled/', $html);

        $manifest->assertAssetRequired('bulk-actions::bulk-actions');
    }

    #[Test]
    public function it_leaves_out_actions_the_user_cannot_run(): void
    {
        $this->actingAs($this->author());

        $html = $this->renderTable();

        $this->assertStringNotContainsString('ޝާއިޢުކުރޭ', $html);
        $this->assertStringContainsString('Delete', $html);
    }

    #[Test]
    public function it_carries_the_listing_filters_for_all_matching(): void
    {
        $this->actingAs($this->admin());

        $this->app['request']->query->replace(['search' => 'މާލެ', 'status' => ['draft', 'published'], 'page' => '2']);

        $html = $this->renderTable();

        $this->assertStringContainsString('name="filters[search]" value="މާލެ"', $html);
        $this->assertStringContainsString('name="filters[status][0]" value="draft"', $html);
        $this->assertStringContainsString('name="filters[status][1]" value="published"', $html);
        $this->assertStringNotContainsString('filters[page]', $html);
        $this->assertMatchesRegularExpression('/<fieldset[^>]*data-bulk-actions-filters[^>]*disabled/', $html);
    }

    #[Test]
    public function it_shows_the_result_of_the_last_action(): void
    {
        $this->actingAs($this->admin());

        session()->flash('bulk-actions.results.articles', (new ActionResult('delete', succeeded: 2, skipped: 1, message: '2 ފޮހެލެވިއްޖެ'))->toArray());

        $html = $this->renderTable();

        $this->assertStringContainsString('2 ފޮހެލެވިއްޖެ', $html);
        $this->assertStringContainsString('data-bulk-actions-result', $html);
        $this->assertStringNotContainsString('data-bulk-actions-progress', $html);
    }

    #[Test]
    public function it_shows_progress_for_queued_runs(): void
    {
        $this->actingAs($this->admin());

        session()->flash('bulk-actions.results.articles', (new ActionResult('delete', status: 'queued', total: 1000, succeeded: 250, message: 'ފަހުން', run: 'abc', run_url: url('bulk-actions/runs/abc')))->toArray());

        $html = $this->renderTable();

        $this->assertStringContainsString('data-run-url="' . url('bulk-actions/runs/abc') . '"', $html);
        $this->assertStringContainsString('value="25"', $html);
        $this->assertStringContainsString('250 of 1000 done', $html);
    }

    #[Test]
    public function it_reopens_the_dialog_after_a_validation_error(): void
    {
        $this->actingAs($this->admin());

        $this->app['request']->setLaravelSession($this->app['session.store']);
        session()->flashInput(['action' => 'change_status', 'articles' => ['1']]);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['status' => 'Pick one']));
        $this->app['view']->share('errors', $errors);

        $html = $this->renderTable();

        $this->assertMatchesRegularExpression('/data-bulk-actions-dialog="change_status"[^>]*data-bulk-actions-reopen="1"|data-bulk-actions-reopen="1"[^>]*data-bulk-actions-dialog="change_status"/', $html);
        $this->assertStringContainsString('Pick one', $html);
    }

    #[Test]
    public function it_renders_nothing_but_the_redirect_without_actions(): void
    {
        $this->actingAs($this->author());

        $html = Blade::render('<x-bulk-actions::actions :actions="$bulkActions" />', [
            'bulkActions' => BulkActions::make()->model(Article::class)->url('/x'),
        ]);

        $this->assertStringNotContainsString('name="action"', $html);
        $this->assertStringContainsString('name="redirect"', $html);
    }

    #[Test]
    public function it_renders_for_the_configured_stack(): void
    {
        $this->actingAs($this->admin());

        $bulk_actions = ArticleBulkActions::make();

        $this->assertIsArray($bulk_actions->render('inertia'));
        $this->assertStringContainsString('data-bulk-actions="articles"', (string) $bulk_actions->render());
        $this->assertStringContainsString('data-bulk-actions="articles"', $bulk_actions->toHtml());
        $this->assertStringContainsString('data-bulk-actions="articles"', (string) $bulk_actions);
        $this->assertEquals($bulk_actions->toArray(), $bulk_actions->jsonSerialize());
    }
}
