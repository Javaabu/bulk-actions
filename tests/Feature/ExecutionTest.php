<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Events\BulkActionCompleted;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Jobs\RunBulkAction;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ExecutionTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
            $router->bulkActionRuns();
            $router->get('articles', fn () => 'index')->name('articles.index');
        });
    }

    #[Test]
    public function it_registers_the_bulk_route_and_finds_its_url(): void
    {
        $this->assertEquals(url('articles/bulk'), ArticleBulkActions::make()->getUrl());
        $this->assertEquals('https://example.com/x', BulkActions::make()->url('https://example.com/x')->getUrl());
    }

    #[Test]
    public function it_needs_a_url_for_unregistered_bulk_actions(): void
    {
        $this->expectException(BulkActionsException::class);

        BulkActions::make()->model(Article::class)->getUrl();
    }

    #[Test]
    public function it_runs_an_action_on_the_selected_records_and_redirects_back_with_the_result(): void
    {
        $admin = $this->admin();
        [$first, $second, $third] = $this->articles(3)->all();

        $this->actingAs($admin)
            ->post('articles/bulk', [
                'action' => 'publish',
                'articles' => [$first->id, $second->id],
                'redirect' => url('articles?page=2'),
            ])
            ->assertRedirect(url('articles?page=2'))
            ->assertSessionHas('bulk-actions.results.articles', fn (array $result) => $result['succeeded'] === 2
                && $result['status'] === 'completed'
                && $result['tone'] === 'success'
                && $result['message'] === '2 ލިޔުން ޝާއިޢުކުރެވިއްޖެ');

        $this->assertDatabaseHas('articles', ['id' => $first->id, 'status' => 'published']);
        $this->assertDatabaseHas('articles', ['id' => $second->id, 'status' => 'published']);
        $this->assertDatabaseHas('articles', ['id' => $third->id, 'status' => 'draft']);
    }

    #[Test]
    public function it_returns_json_for_requests_that_want_json(): void
    {
        $admin = $this->admin();
        $articles = $this->articles(2);

        $this->actingAs($admin)
            ->postJson('articles/bulk', [
                'action' => 'delete',
                'articles' => $articles->pluck('id')->all(),
            ])
            ->assertOk()
            ->assertJson([
                'action' => 'delete',
                'status' => 'completed',
                'total' => 2,
                'succeeded' => 2,
                'message' => '2 records deleted.',
            ]);

        $this->assertSoftDeleted('articles', ['id' => $articles[0]->id]);
    }

    #[Test]
    public function it_ignores_unsafe_redirects(): void
    {
        $admin = $this->admin();
        $articles = $this->articles(1);

        $this->actingAs($admin)
            ->from(url('articles'))
            ->post('articles/bulk', ['action' => 'publish', 'articles' => [$articles[0]->id], 'redirect' => 'https://evil.test/x'])
            ->assertRedirect(url('articles'));

        $this->actingAs($admin)
            ->post('articles/bulk', ['action' => 'publish', 'articles' => [$articles[0]->id], 'redirect' => '/articles?status=draft'])
            ->assertRedirect(url('/articles?status=draft'));
    }

    #[Test]
    public function it_requires_a_known_action(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('articles/bulk', ['action' => 'archive', 'articles' => [1]])
            ->assertSessionHasErrors(['action' => 'Choose an action.']);

        $this->actingAs($admin)
            ->postJson('articles/bulk', ['articles' => [1]])
            ->assertJsonValidationErrors(['action']);
    }

    #[Test]
    public function it_requires_a_selection(): void
    {
        $this->actingAs($this->admin())
            ->post('articles/bulk', ['action' => 'publish', 'articles' => ['']])
            ->assertSessionHasErrors(['articles' => 'Select at least one record.']);
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->postJson('articles/bulk', ['action' => 'publish', 'articles' => [1]])
            ->assertUnauthorized();
    }

    #[Test]
    public function it_only_acts_on_records_in_the_query(): void
    {
        $admin = $this->admin();
        $mine = Article::query()->create(['title' => 'ތިމާގެ', 'user_id' => $admin->id]);
        $other = Article::query()->create(['title' => 'އެހެން', 'user_id' => 999]);

        $bulk_actions = BulkActions::make([
            Action::make('publish')->handle(fn (Collection $records) => $records->each->publish()),
        ])->query(fn () => Article::query()->where('user_id', $admin->id))->user($admin);

        request()->merge(['action' => 'publish', 'articles' => [$mine->id, $other->id]]);

        $result = $bulk_actions->execute();

        $this->assertEquals(1, $result->total);
        $this->assertEquals(1, $result->succeeded);
        $this->assertDatabaseHas('articles', ['id' => $other->id, 'status' => 'draft']);
    }

    #[Test]
    public function it_runs_on_all_records_matching_the_filters(): void
    {
        $admin = $this->admin();
        Article::query()->create(['title' => 'މާލެ ރިޕޯޓް']);
        Article::query()->create(['title' => 'މާލެ ޚަބަރު']);
        Article::query()->create(['title' => 'އައްޑު ރިޕޯޓް']);

        $this->actingAs($admin)
            ->postJson('articles/bulk', [
                'action' => 'publish',
                'all' => 1,
                'filters' => ['search' => 'މާލެ'],
            ])
            ->assertJson(['total' => 2, 'succeeded' => 2]);

        $this->assertEquals(2, Article::query()->where('status', 'published')->count());
        $this->assertDatabaseHas('articles', ['title' => 'އައްޑު ރިޕޯޓް', 'status' => 'draft']);
    }

    #[Test]
    public function it_runs_on_all_records_when_there_are_no_filters(): void
    {
        $articles = $this->articles(3);

        $bulk_actions = BulkActions::make([
            Action::make('publish')->handle(fn (Collection $records) => $records->each->publish()),
        ])->model(Article::class)->user($this->admin());

        request()->merge(['action' => 'publish', 'all' => '1', 'filters' => ['search' => 'x']]);

        $this->assertEquals(3, $bulk_actions->execute()->succeeded);
        $this->assertEquals(3, $articles->first()->newQuery()->where('status', 'published')->count());
    }

    #[Test]
    public function it_handles_the_records_in_chunks(): void
    {
        $articles = $this->articles(5);
        $chunks = [];

        $bulk_actions = BulkActions::make([
            Action::make('count')->chunkSize(2)->handle(function (Collection $records) use (&$chunks) {
                $chunks[] = $records->count();
            }),
        ])->query(Article::query()->latest('id'))->user($this->admin());

        request()->merge(['action' => 'count', 'articles' => $articles->pluck('id')->all()]);

        $result = $bulk_actions->execute();

        $this->assertEquals([2, 2, 1], $chunks);
        $this->assertEquals(5, $result->succeeded);
    }

    #[Test]
    public function it_counts_records_that_fail(): void
    {
        $articles = $this->articles(3);

        $bulk_actions = BulkActions::make([
            Action::make('each')->each(function (Article $article) use ($articles) {
                if ($article->is($articles[1])) {
                    throw new RuntimeException('ނުކުރެވުނު');
                }
            }),
            Action::make('chunk')->handle(fn () => throw new RuntimeException('ނުކުރެވުނު')),
            Action::make('partial')->handle(fn (Collection $records) => 1),
        ])->model(Article::class)->user($this->admin());

        request()->merge(['action' => 'each', 'articles' => $articles->pluck('id')->all()]);
        $result = $bulk_actions->execute();
        $this->assertEquals([2, 1], [$result->succeeded, $result->failed]);
        $this->assertEquals('warning', $result->tone());
        $this->assertStringContainsString('1 record failed.', $result->message);

        request()->merge(['action' => 'chunk']);
        $result = $bulk_actions->execute();
        $this->assertEquals([0, 3], [$result->succeeded, $result->failed]);
        $this->assertEquals('danger', $result->tone());

        request()->merge(['action' => 'partial']);
        $result = $bulk_actions->execute();
        $this->assertEquals([1, 2], [$result->succeeded, $result->failed]);
    }

    #[Test]
    public function it_needs_a_handler(): void
    {
        $articles = $this->articles(1);

        $bulk_actions = BulkActions::make([Action::make('nothing')])->model(Article::class)->user($this->admin());

        request()->merge(['action' => 'nothing', 'articles' => [$articles[0]->id]]);

        $result = $bulk_actions->execute();

        $this->assertEquals(1, $result->failed);
        $this->assertFalse($bulk_actions->getAction('nothing')->hasHandler());
    }

    #[Test]
    public function it_dispatches_an_event_when_an_action_completes(): void
    {
        Event::fake([BulkActionCompleted::class]);

        $admin = $this->admin();
        $articles = $this->articles(1);

        $this->actingAs($admin)->postJson('articles/bulk', ['action' => 'publish', 'articles' => [$articles[0]->id]]);

        Event::assertDispatched(BulkActionCompleted::class, fn (BulkActionCompleted $event) => $event->bulk_actions === ArticleBulkActions::class
            && $event->action === 'publish'
            && $event->result->succeeded === 1
            && $event->selection->ids == [$articles[0]->id]
            && $event->user->is($admin)
            && $event->run === null);
    }

    #[Test]
    public function it_restores_and_force_deletes_trashed_records(): void
    {
        $admin = $this->admin();
        [$trashed, $kept] = $this->articles(2)->all();
        $trashed->delete();

        $this->actingAs($admin)
            ->postJson('articles/bulk', ['action' => 'restore', 'articles' => [$trashed->id, $kept->id]])
            ->assertJson(['total' => 1, 'succeeded' => 1, 'message' => '1 record restored.']);

        $this->assertNotSoftDeleted('articles', ['id' => $trashed->id]);

        $trashed->delete();

        $this->actingAs($admin)
            ->postJson('articles/bulk', ['action' => 'force_delete', 'articles' => [$trashed->id, $kept->id]])
            ->assertJson(['total' => 2, 'succeeded' => 2, 'message' => '2 records permanently deleted.']);

        $this->assertDatabaseCount('articles', 0);
    }

    #[Test]
    public function it_reads_the_flashed_result_for_the_next_page(): void
    {
        $bulk_actions = ArticleBulkActions::make();

        $this->assertNull($bulk_actions->getResult());

        session()->flash($bulk_actions->sessionKey(), (new ActionResult('publish', succeeded: 2, message: 'ހަމަ'))->toArray());

        $this->assertEquals('ހަމަ', $bulk_actions->getResult()->message);
        $this->assertEquals('ހަމަ', $bulk_actions->toArray()['result']['message']);
    }

    #[Test]
    public function it_refuses_to_queue_inline_bulk_actions(): void
    {
        Queue::fake();

        $articles = $this->articles(2);

        $bulk_actions = BulkActions::make([
            Action::make('publish')->queue()->handle(fn () => null),
        ])->model(Article::class)->user($this->admin());

        request()->merge(['action' => 'publish', 'articles' => $articles->pluck('id')->all()]);

        $this->expectException(BulkActionsException::class);

        $bulk_actions->execute();

        Queue::assertNotPushed(RunBulkAction::class);
    }
}
