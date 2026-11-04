<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Javaabu\BulkActions\Enums\RunStatuses;
use Javaabu\BulkActions\Events\BulkActionCompleted;
use Javaabu\BulkActions\Events\BulkActionFailed;
use Javaabu\BulkActions\Events\BulkActionQueued;
use Javaabu\BulkActions\Jobs\RunBulkAction;
use Javaabu\BulkActions\Models\BulkActionRun;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class QueuedRunsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
            $router->bulkActionRuns();
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('bulk-actions.queue_after', 2);
    }

    #[Test]
    public function it_queues_large_selections(): void
    {
        Queue::fake();
        Event::fake([BulkActionQueued::class]);

        $admin = $this->admin();
        $articles = $this->articles(3);

        $this->actingAs($admin)
            ->postJson('articles/bulk', ['action' => 'publish', 'articles' => $articles->pluck('id')->all()])
            ->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
                'tone' => 'info',
                'total' => 3,
                'message' => 'ޝާއިޢުކުރޭ will run on 3 records in the background.',
            ]);

        $run = BulkActionRun::query()->sole();

        $this->assertEquals(RunStatuses::PENDING, $run->status);
        $this->assertEquals(ArticleBulkActions::class, $run->bulk_actions);
        $this->assertEquals('articles', $run->name);
        $this->assertEquals('publish', $run->action);
        $this->assertTrue($run->isOwnedBy($admin));
        $this->assertEquals(url("bulk-actions/runs/{$run->uuid}"), $run->statusUrl());
        $this->assertEquals(0, Article::query()->where('status', 'published')->count());

        Queue::assertPushed(RunBulkAction::class, fn (RunBulkAction $job) => $job->run->is($run));
        Event::assertDispatched(BulkActionQueued::class);
    }

    #[Test]
    public function it_runs_queued_actions_with_progress_and_the_form_data(): void
    {
        Event::fake([BulkActionCompleted::class]);

        $admin = $this->admin();
        $articles = $this->articles(3);

        $this->actingAs($admin)
            ->post('articles/bulk', [
                'action' => 'change_status',
                'articles' => $articles->pluck('id')->all(),
                'status' => 'published',
            ])
            ->assertSessionHas('bulk-actions.results.articles', fn (array $result) => $result['status'] === 'completed' && $result['run'] !== null && $result['run_url'] !== null);

        $run = BulkActionRun::query()->sole();

        $this->assertEquals(RunStatuses::COMPLETED, $run->status);
        $this->assertEquals(['status' => 'published', 'note' => null], $run->data);
        $this->assertEquals(3, $run->succeeded);
        $this->assertEquals(100, $run->progress());
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->finished_at);
        $this->assertEquals('ހާލަތު ބަދަލުކުރޭ: 3 records done.', $run->message);
        $this->assertEquals(3, Article::query()->where('status', 'published')->count());

        Event::assertDispatched(BulkActionCompleted::class, fn (BulkActionCompleted $event) => $event->run?->is($run) && $event->user->is($admin));
    }

    #[Test]
    public function it_authorizes_queued_records_for_the_user_who_started_the_run(): void
    {
        $author = $this->author();
        $mine = $this->articles(2, ['user_id' => $author->id]);
        $others = $this->articles(2, ['user_id' => 999]);

        $this->actingAs($author)
            ->postJson('articles/bulk', ['action' => 'delete', 'articles' => [...$mine->pluck('id'), ...$others->pluck('id')]])
            ->assertOk()
            ->assertJson(['status' => 'completed', 'succeeded' => 2, 'skipped' => 2]);

        $run = BulkActionRun::query()->sole();

        $this->assertEquals([2, 2], [$run->succeeded, $run->skipped]);
        $this->assertEquals(2, Article::query()->count());
    }

    #[Test]
    public function it_runs_queued_actions_in_the_locale_they_were_started_in(): void
    {
        $this->app->setLocale('dv');

        $this->actingAs($this->admin())
            ->postJson('articles/bulk', ['action' => 'delete', 'articles' => $this->articles(3)->pluck('id')->all()]);

        $run = BulkActionRun::query()->sole();

        $this->assertEquals('dv', $run->locale);
        $this->assertEquals(__('bulk-actions::strings.actions.delete.done', [], 'dv') !== '', true);
        $this->assertStringContainsString('3', $run->message);
    }

    #[Test]
    public function it_skips_runs_that_are_no_longer_pending(): void
    {
        $run = $this->pendingRun();
        $run->cancel();

        (new RunBulkAction($run))->handle();

        $this->assertEquals(RunStatuses::CANCELLED, $run->fresh()->status);
        $this->assertEquals(0, Article::query()->where('status', 'published')->count());
    }

    #[Test]
    public function it_stops_when_the_run_is_cancelled(): void
    {
        $run = $this->pendingRun(['chunk' => true]);

        ArticleBulkActions::$after_chunk = function () use ($run) {
            BulkActionRun::query()->whereKey($run->id)->update(['status' => RunStatuses::CANCELLED]);
        };

        try {
            (new RunBulkAction($run))->handle();
        } finally {
            ArticleBulkActions::$after_chunk = null;
        }

        $run->refresh();

        $this->assertEquals(RunStatuses::CANCELLED, $run->status);
        $this->assertEquals(1, $run->succeeded);
        $this->assertEquals(1, Article::query()->where('status', 'published')->count());
    }

    #[Test]
    public function it_marks_failed_runs(): void
    {
        Event::fake([BulkActionFailed::class]);

        $run = $this->pendingRun();

        (new RunBulkAction($run))->failed(new RuntimeException('ނުކުރެވުނު'));

        $run->refresh();

        $this->assertEquals(RunStatuses::FAILED, $run->status);
        $this->assertEquals('ނުކުރެވުނު', $run->error);
        $this->assertEquals('failed', $run->toResult()->status);
        Event::assertDispatched(BulkActionFailed::class);

        (new RunBulkAction($run))->failed(new RuntimeException('again'));
        $this->assertEquals('ނުކުރެވުނު', $run->fresh()->error);
    }

    #[Test]
    public function it_fails_when_the_action_no_longer_exists(): void
    {
        $run = $this->pendingRun();
        $run->forceFill(['action' => 'archive'])->save();

        $this->expectException(RuntimeException::class);

        (new RunBulkAction($run))->handle();
    }

    #[Test]
    public function it_shows_the_status_of_a_run_to_its_owner(): void
    {
        $run = $this->pendingRun();

        $this->actingAs($run->user)
            ->getJson("bulk-actions/runs/{$run->uuid}")
            ->assertOk()
            ->assertJson(['data' => [
                'id' => $run->uuid,
                'action' => 'publish',
                'status' => 'pending',
                'status_label' => 'Waiting',
                'total' => 2,
                'processed' => 0,
                'progress' => 0,
                'is_finished' => false,
                'is_cancellable' => true,
                'cancel_url' => url("bulk-actions/runs/{$run->uuid}/cancel"),
            ]]);

        $this->actingAs($this->author())
            ->getJson("bulk-actions/runs/{$run->uuid}")
            ->assertForbidden();

        $this->actingAs($run->user)
            ->getJson('bulk-actions/runs/nope')
            ->assertNotFound();
    }

    #[Test]
    public function it_lets_others_with_permission_follow_and_cancel_runs(): void
    {
        $run = $this->pendingRun();
        $other = $this->author();

        Gate::define('view_others_bulk_action_runs', fn () => true);
        Gate::define('cancel_others_bulk_action_runs', fn () => true);

        $this->actingAs($other)->getJson("bulk-actions/runs/{$run->uuid}")->assertOk();
        $this->actingAs($other)->postJson("bulk-actions/runs/{$run->uuid}/cancel")->assertOk()->assertJson(['data' => ['status' => 'cancelled']]);
    }

    #[Test]
    public function it_cancels_a_run(): void
    {
        $run = $this->pendingRun();

        $this->actingAs($this->author())
            ->postJson("bulk-actions/runs/{$run->uuid}/cancel")
            ->assertForbidden();

        $this->actingAs($run->user)
            ->from('/articles')
            ->post("bulk-actions/runs/{$run->uuid}/cancel")
            ->assertRedirect('/articles');

        $run->refresh();

        $this->assertEquals(RunStatuses::CANCELLED, $run->status);
        $this->assertFalse($run->cancel());
        $this->assertEquals('The action was cancelled.', $run->message);
    }

    #[Test]
    public function it_prunes_old_finished_runs(): void
    {
        $old = $this->pendingRun();
        $old->cancel();
        BulkActionRun::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(8)]);

        $recent = $this->pendingRun();
        $recent->cancel();

        $pending = $this->pendingRun();
        BulkActionRun::query()->whereKey($pending->id)->update(['updated_at' => now()->subDays(8)]);

        $this->assertEquals([$old->id], (new BulkActionRun)->prunable()->pluck('id')->all());
    }

    #[Test]
    public function it_reports_progress(): void
    {
        $run = new BulkActionRun(['total' => 0]);
        $this->assertNull($run->progress());

        $run->forceFill(['total' => 3, 'succeeded' => 1, 'skipped' => 1, 'status' => RunStatuses::PROCESSING]);
        $this->assertEquals(66, $run->progress());
        $this->assertEquals('Running', $run->status->label());
        $this->assertEquals('info', $run->status->color());
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function pendingRun(array $options = []): BulkActionRun
    {
        $admin = $this->admin();
        $articles = $this->articles(2);

        $run = new BulkActionRun;
        $run->forceFill([
            'bulk_actions' => ArticleBulkActions::class,
            'name' => 'articles',
            'action' => ($options['chunk'] ?? false) ? 'publish_one_by_one' : 'publish',
            'selection' => ['ids' => $articles->pluck('id')->all(), 'all' => false, 'filters' => []],
            'total' => 2,
        ]);
        $run->user()->associate($admin);
        $run->save();

        return $run;
    }
}
