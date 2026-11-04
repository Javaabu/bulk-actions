<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;
use PHPUnit\Framework\Attributes\Test;

class FormsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->group(function () use ($router) {
            $router->bulkActions('articles', ArticleBulkActions::class);
        });
    }

    #[Test]
    public function it_validates_the_action_form(): void
    {
        $articles = $this->articles(2);

        $this->actingAs($this->admin())
            ->post('articles/bulk', ['action' => 'change_status', 'articles' => $articles->pluck('id')->all(), 'status' => 'archived'])
            ->assertSessionHasErrors(['status']);

        $this->actingAs($this->admin())
            ->postJson('articles/bulk', ['action' => 'change_status', 'articles' => $articles->pluck('id')->all()])
            ->assertJsonValidationErrors(['status']);

        $this->assertEquals(0, Article::query()->where('status', 'published')->count());
    }

    #[Test]
    public function it_passes_the_validated_data_to_the_handler(): void
    {
        $articles = $this->articles(2);

        $this->actingAs($this->admin())
            ->postJson('articles/bulk', [
                'action' => 'change_status',
                'articles' => $articles->pluck('id')->all(),
                'status' => 'published',
                'note' => 'ބަދަލުކުރީ',
            ])
            ->assertOk()
            ->assertJson(['succeeded' => 2]);

        $this->assertEquals(2, Article::query()->where('status', 'published')->count());
    }

    #[Test]
    public function it_only_validates_the_chosen_actions_form(): void
    {
        $articles = $this->articles(1);
        $received = null;

        $bulk_actions = BulkActions::make([
            Action::make('a')->form([TextInput::make('reason')->required()])->each(fn () => null),
            Action::make('b')->form([Select::make('level')->options(['1' => 'އެއް'])->required()])->each(function ($record, array $data) use (&$received) {
                $received = $data;
            }),
        ])->model(Article::class)->user($this->admin());

        request()->merge(['action' => 'b', 'articles' => [$articles[0]->id], 'level' => '1', 'reason' => '']);

        $bulk_actions->execute();

        $this->assertEquals(['level' => '1'], $received);
    }

    #[Test]
    public function it_sends_the_form_schema_to_the_ui(): void
    {
        $this->actingAs($this->admin());

        $actions = collect(ArticleBulkActions::make()->toArray()['actions'])->keyBy('name');

        $form = $actions['change_status']['form'];

        $this->assertEquals(1, $form['stencil']);
        $this->assertTrue($form['embedded']);
        $this->assertEquals(['status', 'note'], array_column($form['components'], 'name'));
        $this->assertEquals('ހާލަތު ބަދަލުކުރޭ', $actions['change_status']['confirmation']['heading']);
        $this->assertNull($actions['change_status']['confirmation']['text']);
        $this->assertNull($actions['delete']['form']);
        $this->assertEquals('Are you sure you want to delete the selected records?', $actions['delete']['confirmation']['text']);
        $this->assertNull($actions['restore']['confirmation']);
    }
}
