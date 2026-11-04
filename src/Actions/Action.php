<?php

namespace Javaabu\BulkActions\Actions;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\Stencil\Components\Component;
use Javaabu\Stencil\Concerns\EvaluatesClosures;
use Javaabu\Stencil\Form;

/**
 * One bulk action: what it's called, who may run it, what it asks for and
 * what it does with the selected records.
 *
 * ```php
 * Action::make('publish')
 *     ->label(__('Publish'))
 *     ->can('publish_articles')
 *     ->canEach('update')
 *     ->requiresConfirmation()
 *     ->each(fn (Article $article) => $article->publish());
 * ```
 */
class Action
{
    use Conditionable;
    use EvaluatesClosures;
    use Macroable;
    use Tappable;

    protected string|Closure|null $label = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $color = null;

    protected bool|Closure $requires_confirmation = false;

    protected string|Closure|null $confirmation_heading = null;

    protected string|Closure|null $confirmation_text = null;

    protected string|Closure|null $confirm_button_label = null;

    protected string|Closure|null $success_message = null;

    protected string|Closure|null $can = null;

    /** @var array<int, mixed> */
    protected array $can_arguments = [];

    protected string|Closure|null $can_each = null;

    protected bool|Closure $is_visible = true;

    /** @var array<int, Component>|Closure|Form|null */
    protected array|Closure|Form|null $form = null;

    protected ?Closure $chunk_handler = null;

    protected ?Closure $record_handler = null;

    protected ?Closure $modify_query = null;

    protected ?int $chunk_size = null;

    protected bool|int|null $queue = null;

    /** @var array<string, mixed> */
    protected array $meta = [];

    protected ?BulkActions $bulk_actions = null;

    final public function __construct(protected string $name) {}

    public static function make(?string $name = null): static
    {
        $action = new static($name ?? static::getDefaultName() ?? throw BulkActionsException::actionNeedsName(static::class));

        $action->setUp();

        return $action;
    }

    /**
     * The name of a built-in action, used when make() is called without one.
     */
    public static function getDefaultName(): ?string
    {
        return null;
    }

    /**
     * Configure the action. Called by make(); override it in an action class.
     */
    protected function setUp(): void {}

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @internal set by the BulkActions the action is added to
     */
    public function bulkActions(?BulkActions $bulk_actions): static
    {
        $this->bulk_actions = $bulk_actions;

        return $this;
    }

    public function getBulkActions(): ?BulkActions
    {
        return $this->bulk_actions;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string
    {
        $label = $this->evaluate($this->label);

        if (filled($label)) {
            return (string) $label;
        }

        return __(Str::headline($this->name));
    }

    /**
     * An icon name, as javaabu/forms' icon components take it (`trash`, `check`).
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    /**
     * The color of the action's button: javaabu/forms button colors
     * (`primary`, `danger`, `warning`, `success`, …).
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->evaluate($this->color) ?? 'primary';
    }

    /**
     * Ask before running. Pass a string to set the question.
     */
    public function requiresConfirmation(bool|string|Closure $condition = true): static
    {
        if (is_string($condition)) {
            $this->confirmation_text = $condition;
            $condition = true;
        }

        $this->requires_confirmation = $condition;

        return $this;
    }

    public function confirm(bool|string|Closure $condition = true): static
    {
        return $this->requiresConfirmation($condition);
    }

    public function requiresConfirmationBeforeRunning(): bool
    {
        return (bool) $this->evaluate($this->requires_confirmation);
    }

    public function confirmationHeading(string|Closure|null $heading): static
    {
        $this->confirmation_heading = $heading;

        return $this;
    }

    public function getConfirmationHeading(): string
    {
        return $this->evaluate($this->confirmation_heading) ?? $this->getLabel();
    }

    public function confirmationText(string|Closure|null $text): static
    {
        $this->confirmation_text = $text;

        return $this;
    }

    public function getConfirmationText(): string
    {
        return $this->evaluate($this->confirmation_text)
            ?? __('bulk-actions::strings.confirm_text', ['action' => Str::lower($this->getLabel())]);
    }

    public function confirmButtonLabel(string|Closure|null $label): static
    {
        $this->confirm_button_label = $label;

        return $this;
    }

    public function getConfirmButtonLabel(): string
    {
        return $this->evaluate($this->confirm_button_label) ?? $this->getLabel();
    }

    /**
     * The message shown when the action finishes: a string with `:count`
     * (records done), `:skipped`, `:failed` and `:total`, or a closure that
     * gets the `$result`.
     */
    public function successMessage(string|Closure|null $message): static
    {
        $this->success_message = $message;

        return $this;
    }

    public function getSuccessMessage(ActionResult $result): string
    {
        $message = $this->evaluate($this->success_message, ['result' => $result], [ActionResult::class => $result]);

        $replace = [
            'count' => $result->succeeded,
            'skipped' => $result->skipped,
            'failed' => $result->failed,
            'total' => $result->total,
            'action' => $this->getLabel(),
        ];

        if (filled($message)) {
            return Str::of((string) $message)->replace(
                array_map(fn (string $key) => ":{$key}", array_keys($replace)),
                array_map('strval', array_values($replace)),
            )->toString();
        }

        return trans_choice('bulk-actions::strings.done', $result->succeeded, $replace);
    }

    /**
     * Extra data for custom views and React components.
     *
     * @param  array<string, mixed>  $meta
     */
    public function meta(array $meta): static
    {
        $this->meta = [...$this->meta, ...$meta];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Whether the action is listed. This is presentation only: use can() for
     * who may run it, as hidden actions can still be submitted.
     */
    public function visible(bool|Closure $condition = true): static
    {
        $this->is_visible = $condition;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->is_visible = $condition instanceof Closure
            ? fn () => ! $this->evaluate($condition)
            : ! $condition;

        return $this;
    }

    public function isVisible(): bool
    {
        return (bool) $this->evaluate($this->is_visible);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Who may run the action at all, through Laravel's Gate: an ability or
     * permission (`$user->can($ability, $arguments)`), or a closure that gets
     * the `$user`. Users who fail it don't see the action, and the server
     * rejects it.
     */
    public function can(string|Closure|null $ability, mixed $arguments = []): static
    {
        $this->can = $ability;
        $this->can_arguments = Arr::wrap($arguments);

        return $this;
    }

    public function getCan(): string|Closure|null
    {
        return $this->can;
    }

    /**
     * @return array<int, mixed>
     */
    public function getCanArguments(): array
    {
        return $this->can_arguments;
    }

    /**
     * Who may run the action on each record: an ability checked with the
     * record (`$user->can('update', $record)`), or a closure that gets the
     * `$user` and the `$record`. Records that fail it are skipped and counted.
     */
    public function canEach(string|Closure|null $ability): static
    {
        $this->can_each = $ability;

        return $this;
    }

    public function getCanEach(): string|Closure|null
    {
        return $this->can_each;
    }

    /**
     * Whether the user may run the action (its can()).
     */
    public function isAuthorized(?Authorizable $user): bool
    {
        if ($this->can === null) {
            return true;
        }

        if ($this->can instanceof Closure) {
            return (bool) $this->evaluate($this->can, ['user' => $user]);
        }

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows($this->can, $this->can_arguments);
    }

    /**
     * Whether the user may run the action on the record (its canEach()).
     */
    public function isAuthorizedFor(?Authorizable $user, Model $record): bool
    {
        if ($this->can_each === null) {
            return true;
        }

        if ($this->can_each instanceof Closure) {
            return (bool) $this->evaluate($this->can_each, ['user' => $user, 'record' => $record], [$record::class => $record]);
        }

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows($this->can_each, [$record]);
    }

    /**
     * Whether the action is shown to the user: visible and authorized.
     */
    public function isAvailableFor(?Authorizable $user): bool
    {
        return $this->isVisible() && $this->isAuthorized($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    /**
     * Ask for input before running, with stencil components (or a stencil
     * Form). The validated values are passed to the handler as `$data`.
     *
     * @param  array<int, Component>|Closure|Form|null  $form
     */
    public function form(array|Closure|Form|null $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function hasForm(): bool
    {
        return $this->form !== null;
    }

    /**
     * The action's stencil form, embedded (no <form> tag or submit button).
     */
    public function getForm(): ?Form
    {
        if ($this->form === null) {
            return null;
        }

        $form = $this->evaluate($this->form);

        if (! $form instanceof Form) {
            $form = Form::make()->schema($form);
        }

        return $form
            ->id($this->getFormId())
            ->embedded()
            ->submit(false);
    }

    public function getFormId(): string
    {
        $prefix = $this->bulk_actions?->getName() ?? 'bulk';

        return Str::slug("{$prefix}-{$this->name}-form");
    }

    /*
    |--------------------------------------------------------------------------
    | Handling
    |--------------------------------------------------------------------------
    */

    /**
     * What to do with each chunk of selected records. Gets the `$records`
     * (a Collection), the form's `$data` and the `$user`. Return how many
     * succeeded, or nothing when all did.
     */
    public function handle(Closure $handler): static
    {
        $this->chunk_handler = $handler;

        return $this;
    }

    /**
     * What to do with each selected record. Gets the `$record`, the form's
     * `$data` and the `$user`. A record that throws counts as failed and the
     * exception is reported; the others carry on.
     */
    public function each(Closure $handler): static
    {
        $this->record_handler = $handler;

        return $this;
    }

    public function hasHandler(): bool
    {
        return $this->chunk_handler !== null || $this->record_handler !== null;
    }

    /**
     * Run the action on a chunk of records the user may act on. Returns how
     * many succeeded.
     *
     * @param  Collection<int, Model>  $records
     * @param  array<string, mixed>  $data
     */
    public function process(Collection $records, array $data, ?Authorizable $user): int
    {
        if ($this->chunk_handler !== null) {
            $succeeded = $this->evaluate($this->chunk_handler, [
                'records' => $records,
                'data' => $data,
                'user' => $user,
                'action' => $this,
            ], [Collection::class => $records]);

            return is_int($succeeded) ? $succeeded : $records->count();
        }

        if ($this->record_handler === null) {
            throw BulkActionsException::actionHasNoHandler($this->name);
        }

        $succeeded = 0;

        foreach ($records as $record) {
            $this->evaluate($this->record_handler, [
                'record' => $record,
                'data' => $data,
                'user' => $user,
                'action' => $this,
            ], [$record::class => $record]);

            $succeeded++;
        }

        return $succeeded;
    }

    public function processesEachRecord(): bool
    {
        return $this->chunk_handler === null && $this->record_handler !== null;
    }

    /**
     * Change the query the selected records come from, e.g. `withTrashed()`.
     */
    public function modifyQueryUsing(?Closure $callback): static
    {
        $this->modify_query = $callback;

        return $this;
    }

    public function modifyQuery(Builder $query): Builder
    {
        if ($this->modify_query === null) {
            return $query;
        }

        return $this->evaluate($this->modify_query, ['query' => $query], [Builder::class => $query]) ?? $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Chunking and queueing
    |--------------------------------------------------------------------------
    */

    /**
     * How many records are loaded and handled at a time. Default:
     * config('bulk-actions.chunk_size').
     */
    public function chunkSize(?int $size): static
    {
        $this->chunk_size = $size;

        return $this;
    }

    public function getChunkSize(): int
    {
        return max(1, $this->chunk_size ?? $this->bulk_actions?->getChunkSize() ?? (int) config('bulk-actions.chunk_size', 100));
    }

    /**
     * Whether to run on the queue: true always, false never, or a number to
     * queue selections of more records than that. Default: the BulkActions'
     * queueAfter(), then config('bulk-actions.queue_after').
     */
    public function queue(bool|int|null $condition = true): static
    {
        $this->queue = $condition;

        return $this;
    }

    public function queueAfter(?int $count): static
    {
        return $this->queue($count);
    }

    public function shouldQueue(int $count): bool
    {
        $queue = $this->queue ?? $this->bulk_actions?->getQueueAfter() ?? config('bulk-actions.queue_after', 500);

        if (is_bool($queue)) {
            return $queue;
        }

        if ($queue === null) {
            return false;
        }

        return $count > (int) $queue;
    }

    /*
    |--------------------------------------------------------------------------
    | Closures
    |--------------------------------------------------------------------------
    */

    protected function closureArgumentsByName(): array
    {
        return [
            'action' => fn () => $this,
            'bulkActions' => fn () => $this->bulk_actions,
            'model' => fn () => $this->bulk_actions?->getModel(),
            'user' => fn () => $this->bulk_actions?->getUser() ?? request()->user(),
        ];
    }

    protected function closureArgumentsByType(): array
    {
        return [
            self::class => fn () => $this,
            BulkActions::class => fn () => $this->bulk_actions,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    */

    /**
     * The action for the UI: what the Blade views and React components render.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $form = $this->getForm();

        return [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'icon' => $this->getIcon(),
            'color' => $this->getColor(),
            'confirmation' => $this->requiresConfirmationBeforeRunning() || $form ? [
                'heading' => $this->getConfirmationHeading(),
                'text' => $this->requiresConfirmationBeforeRunning() ? $this->getConfirmationText() : null,
                'button' => $this->getConfirmButtonLabel(),
            ] : null,
            'form' => $form?->toArray(),
            'meta' => $this->getMeta() ?: null,
        ];
    }
}
