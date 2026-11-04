<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\Fixtures\Models\User;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthorizationTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
        });
    }

    #[Test]
    public function it_checks_can_with_the_gate(): void
    {
        Gate::define('archive_articles', fn (User $user) => $user->is_admin);

        $action = Action::make('archive')->can('archive_articles');

        $this->assertTrue($action->isAuthorized($this->admin()));
        $this->assertFalse($action->isAuthorized($this->author()));
        $this->assertFalse($action->isAuthorized(null));
        $this->assertEquals('archive_articles', $action->getCan());
    }

    #[Test]
    public function it_passes_can_arguments_to_the_policy(): void
    {
        $action = Action::make('publish')->can('publish', Article::class);

        $this->assertEquals([Article::class], $action->getCanArguments());
        $this->assertTrue($action->isAuthorized($this->admin()));
        $this->assertFalse($action->isAuthorized($this->author()));
    }

    #[Test]
    public function it_checks_can_with_a_closure(): void
    {
        $action = Action::make('archive')->can(fn (?User $user) => $user?->name === 'އަޙްމަދު');

        $this->assertTrue($action->isAuthorized($this->admin()));
        $this->assertFalse($action->isAuthorized($this->author()));
        $this->assertFalse($action->isAuthorized(null));
    }

    #[Test]
    public function it_allows_actions_without_can(): void
    {
        $this->assertTrue(Action::make('x')->isAuthorized(null));
        $this->assertTrue(Action::make('x')->isAuthorizedFor(null, new Article));
    }

    #[Test]
    public function it_checks_each_record_with_the_policy_or_a_closure(): void
    {
        $author = $this->author();
        $mine = Article::query()->create(['title' => 'ތިމާގެ', 'user_id' => $author->id]);
        $other = Article::query()->create(['title' => 'އެހެން', 'user_id' => 999]);

        $action = Action::make('edit')->canEach('update');
        $this->assertTrue($action->isAuthorizedFor($author, $mine));
        $this->assertFalse($action->isAuthorizedFor($author, $other));
        $this->assertFalse($action->isAuthorizedFor(null, $mine));

        $action = Action::make('edit')->canEach(fn (User $user, Article $record) => $record->title === 'އެހެން');
        $this->assertFalse($action->isAuthorizedFor($author, $mine));
        $this->assertTrue($action->isAuthorizedFor($author, $other));
    }

    #[Test]
    public function it_only_lists_the_actions_the_user_can_run(): void
    {
        $this->actingAs($this->author());

        $names = array_keys(ArticleBulkActions::make()->getAvailableActions());

        $this->assertEquals(['delete', 'restore', 'force_delete', 'publish_one_by_one', 'change_status'], $names);

        $this->actingAs($this->admin());

        $this->assertArrayHasKey('publish', ArticleBulkActions::make()->getAvailableActions());
    }

    #[Test]
    public function it_leaves_hidden_actions_out_of_the_ui_but_authorizes_with_can(): void
    {
        $bulk_actions = BulkActions::make([
            Action::make('hidden')->hidden(),
            Action::make('shown'),
        ])->model(Article::class)->url('/bulk');

        $this->assertEquals(['shown'], array_column($bulk_actions->toArray()['actions'], 'name'));
    }

    #[Test]
    public function it_rejects_actions_the_user_cannot_run(): void
    {
        $articles = $this->articles(2);

        $this->actingAs($this->author())
            ->postJson('articles/bulk', ['action' => 'publish', 'articles' => $articles->pluck('id')->all()])
            ->assertForbidden();

        $this->assertEquals(0, Article::query()->where('status', 'published')->count());
    }

    #[Test]
    public function it_skips_records_the_user_cannot_act_on(): void
    {
        $author = $this->author();
        $mine = $this->articles(2, ['user_id' => $author->id]);
        $others = $this->articles(1, ['user_id' => 999]);

        $this->actingAs($author)
            ->postJson('articles/bulk', [
                'action' => 'delete',
                'articles' => [...$mine->pluck('id')->all(), ...$others->pluck('id')->all()],
            ])
            ->assertOk()
            ->assertJson([
                'total' => 3,
                'succeeded' => 2,
                'skipped' => 1,
                'tone' => 'warning',
                'message' => '2 records deleted. 1 record was skipped because you aren\'t allowed to change it.',
            ]);

        $this->assertSoftDeleted('articles', ['id' => $mine[0]->id]);
        $this->assertNotSoftDeleted('articles', ['id' => $others[0]->id]);
    }

    #[Test]
    public function it_authorizes_each_record_for_the_given_user(): void
    {
        $author = $this->author();
        $articles = $this->articles(2, ['user_id' => 999]);

        $bulk_actions = BulkActions::make([
            DeleteAction::make(),
            Action::make('touch')->canEach('update')->handle(fn (Collection $records) => null),
        ])->model(Article::class)->user($author);

        request()->merge(['action' => 'delete', 'articles' => $articles->pluck('id')->all()]);

        $result = $bulk_actions->execute();

        $this->assertEquals(0, $result->succeeded);
        $this->assertEquals(2, $result->skipped);
        $this->assertEquals('danger', $result->tone());
        $this->assertEquals('No records were deleted. 2 records were skipped because you aren\'t allowed to change them.', $result->message);
    }
}
