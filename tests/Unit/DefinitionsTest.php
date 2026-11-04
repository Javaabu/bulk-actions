<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Illuminate\Support\Collection;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Actions\DeleteAction;
use Javaabu\BulkActions\Actions\ForceDeleteAction;
use Javaabu\BulkActions\Actions\RestoreAction;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Tests\Fixtures\BulkActions\ArticleBulkActions;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\Fixtures\Models\Tag;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;
use PHPUnit\Framework\Attributes\Test;

class DefinitionsTest extends TestCase
{
    #[Test]
    public function it_builds_inline_bulk_actions(): void
    {
        $bulk_actions = BulkActions::make([
            DeleteAction::make(),
            Action::make('publish')->handle(fn (Collection $records) => null),
            null,
        ])->model(Article::class);

        $this->assertEquals(['delete', 'publish'], array_keys($bulk_actions->getActions()));
        $this->assertSame($bulk_actions, $bulk_actions->getAction('delete')->getBulkActions());
        $this->assertTrue($bulk_actions->hasAction('publish'));
        $this->assertNull($bulk_actions->getAction('archive'));
        $this->assertTrue($bulk_actions->isInline());
    }

    #[Test]
    public function it_builds_bulk_actions_classes(): void
    {
        $bulk_actions = ArticleBulkActions::make();

        $this->assertEquals(['delete', 'restore', 'force_delete', 'publish', 'publish_one_by_one', 'change_status'], array_keys($bulk_actions->getActions()));
        $this->assertEquals(Article::class, $bulk_actions->getModel());
        $this->assertFalse($bulk_actions->isInline());
    }

    #[Test]
    public function it_rejects_duplicate_action_names(): void
    {
        $this->expectException(BulkActionsException::class);

        BulkActions::make([DeleteAction::make(), DeleteAction::make()])->getActions();
    }

    #[Test]
    public function it_needs_a_name_for_custom_actions(): void
    {
        $this->expectException(BulkActionsException::class);

        Action::make();
    }

    #[Test]
    public function it_names_the_selection_after_the_model(): void
    {
        $this->assertEquals('articles', BulkActions::make()->model(Article::class)->getName());
        $this->assertEquals('tags', BulkActions::make()->query(Tag::query())->getName());
        $this->assertEquals('posts', BulkActions::make()->model(Article::class)->name('posts')->getName());
        $this->assertEquals('records', BulkActions::make()->getName());
    }

    #[Test]
    public function it_takes_the_query_from_the_model_a_builder_or_a_closure(): void
    {
        $this->assertEquals(Article::class, BulkActions::make()->model(Article::class)->newQuery()->getModel()::class);
        $this->assertEquals(Tag::class, BulkActions::make()->query(Tag::query())->newQuery()->getModel()::class);
        $this->assertEquals(Tag::class, BulkActions::make()->query(fn () => Tag::query())->newQuery()->getModel()::class);
    }

    #[Test]
    public function it_needs_a_model_or_a_query(): void
    {
        $this->expectException(BulkActionsException::class);

        BulkActions::make()->newQuery();
    }

    #[Test]
    public function it_gives_actions_labels_icons_colors_and_meta(): void
    {
        $action = Action::make('mark_as_read')
            ->icon('check')
            ->color('success')
            ->meta(['shortcut' => 'r']);

        $this->assertEquals('Mark As Read', $action->getLabel());
        $this->assertEquals('check', $action->getIcon());
        $this->assertEquals('success', $action->getColor());
        $this->assertEquals(['shortcut' => 'r'], $action->getMeta());

        $action->label(fn () => 'ކިޔައިފިން');

        $this->assertEquals('ކިޔައިފިން', $action->getLabel());
        $this->assertEquals('primary', Action::make('x')->getColor());
    }

    #[Test]
    public function it_configures_confirmation(): void
    {
        $action = Action::make('archive');

        $this->assertFalse($action->requiresConfirmationBeforeRunning());

        $action->requiresConfirmation('ޔަޤީންތޯ؟');

        $this->assertTrue($action->requiresConfirmationBeforeRunning());
        $this->assertEquals('ޔަޤީންތޯ؟', $action->getConfirmationText());
        $this->assertEquals('Archive', $action->getConfirmationHeading());
        $this->assertEquals('Archive', $action->getConfirmButtonLabel());

        $action->confirm(false)->confirmationHeading('ހިފަހައްޓާ')->confirmButtonLabel('އާން');

        $this->assertFalse($action->requiresConfirmationBeforeRunning());
        $this->assertEquals('ހިފަހައްޓާ', $action->getConfirmationHeading());
        $this->assertEquals('އާން', $action->getConfirmButtonLabel());
        $this->assertEquals('Are you sure you want to archive the selected records?', Action::make('archive')->confirm()->getConfirmationText());
    }

    #[Test]
    public function it_builds_success_messages(): void
    {
        $result = new ActionResult('publish', succeeded: 3, skipped: 1, total: 4);

        $this->assertEquals('3 published, 1 skipped of 4', Action::make('publish')->successMessage(':count published, :skipped skipped of :total')->getSuccessMessage($result));
        $this->assertEquals('Publish: 3 records done.', Action::make('publish')->getSuccessMessage($result));
        $this->assertEquals('3!', Action::make('publish')->successMessage(fn (ActionResult $result) => "{$result->succeeded}!")->getSuccessMessage($result));
    }

    #[Test]
    public function it_has_built_in_delete_restore_and_force_delete(): void
    {
        $bulk_actions = BulkActions::make([DeleteAction::make(), RestoreAction::make(), ForceDeleteAction::make()])->model(Article::class);

        $delete = $bulk_actions->getAction('delete');
        $restore = $bulk_actions->getAction('restore');
        $force_delete = $bulk_actions->getAction('force_delete');

        $this->assertEquals('Delete', $delete->getLabel());
        $this->assertEquals('danger', $delete->getColor());
        $this->assertTrue($delete->requiresConfirmationBeforeRunning());
        $this->assertEquals('delete', $delete->getCanEach());

        $this->assertEquals('Restore', $restore->getLabel());
        $this->assertEquals('restore', $restore->getCanEach());
        $this->assertFalse($restore->requiresConfirmationBeforeRunning());

        $this->assertEquals('Delete permanently', $force_delete->getLabel());
        $this->assertEquals('forceDelete', $force_delete->getCanEach());
        $this->assertTrue($restore->isVisible());
    }

    #[Test]
    public function it_hides_trash_actions_for_models_without_soft_deletes(): void
    {
        $bulk_actions = BulkActions::make([RestoreAction::make(), ForceDeleteAction::make()])->model(Tag::class);

        $this->assertFalse($bulk_actions->getAction('restore')->isVisible());
        $this->assertFalse($bulk_actions->getAction('force_delete')->isAuthorized($this->admin()));
        $this->assertEquals([], $bulk_actions->getAvailableActions());
    }

    #[Test]
    public function it_hides_actions(): void
    {
        $this->assertFalse(Action::make('x')->hidden()->isVisible());
        $this->assertTrue(Action::make('x')->hidden(fn () => false)->isVisible());
        $this->assertFalse(Action::make('x')->visible(fn () => false)->isVisible());
    }

    #[Test]
    public function it_builds_the_action_form_as_an_embedded_stencil_form(): void
    {
        $bulk_actions = ArticleBulkActions::make();
        $form = $bulk_actions->getAction('change_status')->getForm();

        $this->assertInstanceOf(Form::class, $form);
        $this->assertTrue($form->isEmbedded());
        $this->assertNull($form->getSubmitLabel());
        $this->assertEquals('articles-change-status-form', $form->getId());
        $this->assertEquals(['status', 'note'], array_keys($form->getFields()));

        $this->assertNull($bulk_actions->getAction('publish')->getForm());

        $form = Action::make('x')->form(Form::make()->schema([TextInput::make('a')]))->getForm();
        $this->assertEquals(['a'], array_keys($form->getFields()));
        $this->assertTrue(Action::make('x')->form(fn () => [TextInput::make('b')])->hasForm());
    }

    #[Test]
    public function it_decides_when_to_queue(): void
    {
        config(['bulk-actions.queue_after' => 10]);

        $bulk_actions = BulkActions::make([
            $default = Action::make('a'),
            $always = Action::make('b')->queue(),
            $never = Action::make('c')->queue(false),
            $after = Action::make('d')->queueAfter(2),
        ]);

        $bulk_actions->getActions();

        $this->assertFalse($default->shouldQueue(10));
        $this->assertTrue($default->shouldQueue(11));
        $this->assertTrue($always->shouldQueue(1));
        $this->assertFalse($never->shouldQueue(100000));
        $this->assertTrue($after->shouldQueue(3));

        $bulk_actions->queueAfter(1);
        $this->assertTrue($default->shouldQueue(2));
    }

    #[Test]
    public function it_decides_the_chunk_size(): void
    {
        $bulk_actions = BulkActions::make([$a = Action::make('a'), $b = Action::make('b')->chunkSize(5)]);
        $bulk_actions->getActions();

        $this->assertEquals(100, $a->getChunkSize());
        $this->assertEquals(5, $b->getChunkSize());

        $bulk_actions->chunkSize(20);
        $this->assertEquals(20, $a->getChunkSize());
    }

    #[Test]
    public function it_injects_closure_arguments(): void
    {
        $bulk_actions = BulkActions::make([
            $action = Action::make('a')->label(fn (Action $action, BulkActions $bulkActions, ?string $model) => "{$action->getName()}-{$bulkActions->getName()}-{$model}"),
        ])->model(Article::class);

        $bulk_actions->getActions();

        $this->assertEquals('a-articles-' . Article::class, $action->getLabel());
    }

    #[Test]
    public function it_takes_the_total_from_a_paginator(): void
    {
        $this->articles(3);

        $bulk_actions = BulkActions::make()->model(Article::class)->paginator(Article::query()->paginate(2));

        $this->assertEquals(3, $bulk_actions->getTotal());
        $this->assertEquals(2, $bulk_actions->getPageCount());

        $bulk_actions->paginator(Article::query()->simplePaginate(2));
        $this->assertEquals(2, $bulk_actions->getPageCount());
    }
}
