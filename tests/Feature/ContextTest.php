<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Jobs\RunBulkAction;
use Javaabu\BulkActions\Models\BulkActionRun;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\StatusBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

class ContextTest extends TestCase
{
    #[Test]
    public function it_stores_the_context_on_a_queued_run(): void
    {
        Queue::fake();

        $this->articles(2, ['status' => 'draft']);

        request()->merge(['action' => 'archive', 'all' => '1']);

        StatusBulkActions::for('draft')->user($this->admin())->execute();

        $this->assertEquals(['status' => 'draft'], BulkActionRun::query()->sole()->context);
        Queue::assertPushed(RunBulkAction::class);
    }

    #[Test]
    public function it_rebuilds_a_queued_run_from_its_context(): void
    {
        Queue::fake();

        $drafts = $this->articles(2, ['status' => 'draft']);
        $published = $this->articles(1, ['status' => 'published']);

        request()->merge(['action' => 'archive', 'all' => '1']);

        $result = StatusBulkActions::for('draft')->user($this->admin())->execute();

        $this->assertEquals('queued', $result->status);
        $this->assertEquals(2, $result->total);
        $this->assertEquals('draft ފައިލުކުރޭ will run on 2 records in the background.', $result->message);

        // The job only has the run: the class and its context.
        (new RunBulkAction(BulkActionRun::query()->sole()))->handle();

        $run = BulkActionRun::query()->sole();

        $this->assertEquals(2, $run->succeeded);
        $this->assertEquals('draft ފައިލުކުރޭ: 2 records done.', $run->message);
        $this->assertDatabaseHas('articles', ['id' => $drafts[0]->id, 'status' => 'archived_draft']);
        $this->assertDatabaseHas('articles', ['id' => $published[0]->id, 'status' => 'published']);
    }

    #[Test]
    public function it_keeps_context_given_with_with_context_by_default(): void
    {
        $bulk_actions = ArticleBulkActions::make()->withContext(['tenant' => 7])->withContext(['tags' => ['a', 'b'], 'none' => null]);

        $this->assertEquals(['tenant' => 7, 'tags' => ['a', 'b'], 'none' => null], $bulk_actions->context());
        $this->assertEquals(['tenant' => 7], ArticleBulkActions::fromContext(['tenant' => 7])->context());
        $this->assertInstanceOf(ArticleBulkActions::class, ArticleBulkActions::fromContext([]));
        $this->assertEquals([], ArticleBulkActions::make()->context());
    }

    #[Test]
    public function it_runs_queued_sets_without_context_as_before(): void
    {
        Queue::fake();

        $this->articles(1);

        request()->merge(['action' => 'publish', 'all' => '1']);

        config(['bulk-actions.queue_after' => 0]);

        ArticleBulkActions::make()->user($this->admin())->execute();

        $run = BulkActionRun::query()->sole();

        $this->assertNull($run->context);

        (new RunBulkAction($run))->handle();

        $this->assertEquals(1, $run->fresh()->succeeded);
    }

    #[Test]
    public function it_refuses_context_that_cannot_be_stored(): void
    {
        $this->expectException(BulkActionsException::class);
        $this->expectExceptionMessage('[filters.model] is a [stdClass]');

        ArticleBulkActions::make()->withContext(['filters' => ['model' => new stdClass]]);
    }

    #[Test]
    public function it_refuses_to_queue_an_overridden_context_that_cannot_be_stored(): void
    {
        $this->articles(1);

        $bulk_actions = new class extends BulkActions
        {
            protected ?string $model = Article::class;

            public function context(): array
            {
                return ['article' => Article::query()->first()];
            }

            protected function actions(): array
            {
                return [Action::make('touch')->queue()->handle(fn (Collection $records) => null)];
            }
        };

        request()->merge(['action' => 'touch', 'all' => '1']);

        $this->expectException(BulkActionsException::class);

        $bulk_actions::make()->user($this->admin())->execute();
    }
}
