<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Keeps what the JavaScript tests render in step with PHP: the JSON the React
 * components take and the HTML the Blade script enhances. Run with
 * BULK_ACTIONS_UPDATE_FIXTURES=1 to rewrite them.
 */
class JsFixtureTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
        });
    }

    protected function fixture(string $file, string $contents): void
    {
        $path = __DIR__ . "/../js/fixtures/{$file}";

        if (getenv('BULK_ACTIONS_UPDATE_FIXTURES')) {
            file_put_contents($path, $contents);
        }

        // Whitespace differs between Laravel versions; the markup must not.
        $normalize = fn (string $html) => preg_replace('/\s+/', ' ', $html);

        $this->assertEquals($normalize(file_get_contents($path)), $normalize($contents));
    }

    protected function setUpListing(): void
    {
        $this->actingAs($this->admin());

        foreach (range(1, 3) as $i) {
            Article::query()->create(['id' => $i, 'title' => "ލިޔުން {$i}"]);
        }

        $this->app['request']->query->replace(['search' => 'މާލެ', 'page' => '1']);
    }

    #[Test]
    public function it_matches_the_json_the_react_tests_render(): void
    {
        $this->setUpListing();

        $json = ArticleBulkActions::make()->paginator(Article::query()->paginate(2))->toArray();

        $this->fixture('bulk-actions.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    #[Test]
    public function it_matches_the_html_the_blade_script_tests_enhance(): void
    {
        $this->setUpListing();

        $html = Blade::render(<<<'BLADE'
            <x-forms::table model="articles">
                <x-slot:bulk-form :action="$bulkActions->getUrl()">
                    <x-bulk-actions::actions :actions="$bulkActions" :paginator="$articles" />
                </x-slot:bulk-form>
                <x-slot:rows>
                    @foreach($articles as $article)
                        <x-forms::table.row :model="$article" name="articles">
                            <x-forms::table.cell name="title" />
                        </x-forms::table.row>
                    @endforeach
                </x-slot:rows>
            </x-forms::table>
            BLADE, ['bulkActions' => ArticleBulkActions::make(), 'articles' => Article::query()->paginate(2)]);

        // The CSRF token changes every run.
        $html = preg_replace('/name="_token" value="[^"]*"/', 'name="_token" value="test-token"', $html);

        $this->fixture('table.html', $html);
    }
}
