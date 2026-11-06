<?php

namespace Javaabu\BulkActions;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Execution\ActionResult;
use Javaabu\BulkActions\Execution\Executor;
use Javaabu\BulkActions\Routing\BulkActionRoutes;
use Javaabu\BulkActions\Support\FiltersAdapter;
use Javaabu\Stencil\Concerns\EvaluatesClosures;
use JsonSerializable;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bulk actions of an index page. Build one inline with
 * BulkActions::make([...])->model(Article::class), or extend it and return
 * the actions from actions().
 *
 * @implements Arrayable<string, mixed>
 */
class BulkActions implements Arrayable, Htmlable, JsonSerializable, Stringable
{
    use Conditionable;
    use EvaluatesClosures;
    use Macroable;
    use Tappable;

    /** Whether the package loads its migration (the bulk_action_runs table) */
    public static bool $runs_migrations = true;

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    protected ?string $name = null;

    protected string|Closure|null $url = null;

    protected Builder|Closure|null $query = null;

    /** The filters "all matching" applies: a javaabu/filters class or instance, or a closure */
    protected mixed $filters = null;

    protected ?int $chunk_size = null;

    protected bool|int|null $queue_after = null;

    protected ?Authorizable $user = null;

    protected ?int $total = null;

    protected ?int $page_count = null;

    /** @var array<string, mixed> */
    protected array $context = [];

    /** @var array<string, mixed>|null */
    protected ?array $filter_parameters = null;

    /** @var array<int, Action> */
    protected array $added_actions = [];

    /** @var array<string, Action>|null */
    private ?array $resolved_actions = null;

    /**
     * @param  array<int, Action|null>  $actions
     */
    public static function make(array $actions = []): static
    {
        $instance = app(static::class);

        $instance->withActions($actions);

        $instance->setUp();

        return $instance;
    }

    /**
     * Configure the bulk actions. Called by make(); override it in a class.
     */
    protected function setUp(): void {}

    /**
     * The actions of a bulk actions class.
     *
     * @return array<int, Action|null>
     */
    protected function actions(): array
    {
        return [];
    }

    /**
     * @param  array<int, Action|null>  $actions
     */
    public function withActions(array $actions): static
    {
        $this->added_actions = [...$this->added_actions, ...array_filter($actions)];
        $this->resolved_actions = null;

        return $this;
    }

    /**
     * Whether this is an inline BulkActions rather than a class the queue job can rebuild.
     */
    public function isInline(): bool
    {
        return static::class === self::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Context
    |--------------------------------------------------------------------------
    */

    /**
     * Data a class configured at runtime needs to be rebuilt in a queued run
     * (`['post_type' => 'news']`). Scalars, nulls and arrays of them only: it's
     * stored on the BulkActionRun as JSON.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        static::assertSerializableContext($context);

        $this->context = [...$this->context, ...$context];

        return $this;
    }

    /**
     * What a queued run stores to rebuild this set with fromContext(). Default:
     * what was given to withContext(). Override it to derive the context from
     * the set's own state.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Rebuild the set from a queued run's context. Default: make() with the
     * context. Override it when the context configures the set, e.g.
     * `return static::for(PostType::find($context['post_type']));`.
     *
     * @param  array<string, mixed>  $context
     */
    public static function fromContext(array $context): static
    {
        return static::make()->withContext($context);
    }

    /**
     * Throws when the context holds anything but scalars, nulls and arrays.
     *
     * @param  array<array-key, mixed>  $context
     */
    public static function assertSerializableContext(array $context, string $path = ''): void
    {
        foreach ($context as $key => $value) {
            $name = ltrim("{$path}.{$key}", '.');

            if (is_array($value)) {
                static::assertSerializableContext($value, $name);

                continue;
            }

            if ($value === null || is_scalar($value)) {
                continue;
            }

            throw BulkActionsException::contextNotSerializable(static::class, $name, get_debug_type($value));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * @param  class-string<Model>|null  $model
     */
    public function model(?string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        if ($this->model !== null) {
            return $this->model;
        }

        if ($this->query === null) {
            return null;
        }

        return $this->newQuery()->getModel()::class;
    }

    /**
     * The name of the row checkboxes (`articles` for `articles[]`): the
     * `model` of javaabu/forms' table. Default: the model's plural, snake case.
     */
    public function name(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        if ($this->name !== null) {
            return $this->name;
        }

        $model = $this->getModel();

        if ($model === null) {
            return 'records';
        }

        return Str::of(class_basename($model))->snake()->plural()->toString();
    }

    /**
     * Where the bulk action form submits. Default: the route registered for
     * this class with Route::bulkActions().
     */
    public function url(string|Closure|null $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getUrl(): string
    {
        $url = $this->evaluate($this->url);

        if (filled($url)) {
            return $url;
        }

        $route = BulkActionRoutes::routeNameFor(static::class);

        if ($route === null) {
            throw BulkActionsException::missingUrl(static::class);
        }

        return route($route);
    }

    /**
     * The records that may be selected: a query or a closure that returns
     * one. Default: every record of the model. Ids outside it are ignored.
     */
    public function query(Builder|Closure|null $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * A fresh query for the records that may be selected. Override it in a
     * class to scope the records (by tenant, owner, …).
     */
    public function newQuery(): Builder
    {
        if ($this->query instanceof Builder) {
            return clone $this->query;
        }

        if ($this->query instanceof Closure) {
            return $this->evaluate($this->query);
        }

        $model = $this->getModel() ?? throw BulkActionsException::missingModel(static::class);

        return $model::query();
    }

    /**
     * How "all matching" rebuilds the listing's filters: a javaabu/filters
     * class or instance, or a closure that gets the `$query` and the listing's
     * query string as `$filters`.
     */
    public function filters(mixed $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getFilters(): mixed
    {
        return $this->filters;
    }

    /**
     * Apply the listing's filters to the query, for "all matching".
     *
     * @param  array<string, mixed>  $parameters  the listing's query string
     */
    public function applyFilters(Builder $query, array $parameters): Builder
    {
        if ($this->filters instanceof Closure) {
            return $this->evaluate($this->filters, [
                'query' => $query,
                'filters' => $parameters,
                'parameters' => $parameters,
            ], [Builder::class => $query]) ?? $query;
        }

        if ($this->filters !== null) {
            return FiltersAdapter::apply($this->filters, $query, $parameters);
        }

        return $query;
    }

    /**
     * The listing's query string parameters "all matching" sends back.
     * Default: the javaabu/filters' parameters, else the current query string
     * without paging and sorting.
     *
     * @param  array<string, mixed>|null  $parameters
     */
    public function filterParameters(?array $parameters): static
    {
        $this->filter_parameters = $parameters;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilterParameters(): array
    {
        if ($this->filter_parameters !== null) {
            return $this->filter_parameters;
        }

        $parameters = FiltersAdapter::parameters($this->filters);

        if ($parameters !== null) {
            return $parameters;
        }

        return collect(request()->query())
            ->except(config('bulk-actions.ignored_parameters', []))
            ->all();
    }

    public function chunkSize(?int $size): static
    {
        $this->chunk_size = $size;

        return $this;
    }

    public function getChunkSize(): ?int
    {
        return $this->chunk_size;
    }

    /**
     * When the actions run on the queue: true always, false never, or a
     * number to queue selections of more records than that. Actions can
     * override it. Default: config('bulk-actions.queue_after').
     */
    public function queueAfter(bool|int|null $count): static
    {
        $this->queue_after = $count;

        return $this;
    }

    public function getQueueAfter(): bool|int|null
    {
        return $this->queue_after;
    }

    /**
     * The user the actions are authorized for. Default: the request's user.
     */
    public function user(?Authorizable $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?Authorizable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $user = request()->user() ?? auth()->user();

        return $user instanceof Authorizable ? $user : null;
    }

    /**
     * How many records match the listing, to offer "select all matching".
     */
    public function total(?int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * Take the total and the page size from the listing's paginator.
     *
     * @param  Paginator<int, mixed>  $paginator
     */
    public function paginator(Paginator $paginator): static
    {
        $this->page_count = count($paginator->items());

        if ($paginator instanceof LengthAwarePaginator) {
            $this->total = $paginator->total();
        }

        return $this;
    }

    public function getPageCount(): ?int
    {
        return $this->page_count;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * Every action, by name.
     *
     * @return array<string, Action>
     */
    public function getActions(): array
    {
        if ($this->resolved_actions !== null) {
            return $this->resolved_actions;
        }

        $actions = [];

        foreach ([...array_filter($this->actions()), ...$this->added_actions] as $action) {
            if (array_key_exists($action->getName(), $actions)) {
                throw BulkActionsException::duplicateAction($action->getName());
            }

            $actions[$action->getName()] = $action->bulkActions($this);
        }

        return $this->resolved_actions = $actions;
    }

    public function getAction(?string $name): ?Action
    {
        if ($name === null) {
            return null;
        }

        return $this->getActions()[$name] ?? null;
    }

    public function hasAction(string $name): bool
    {
        return $this->getAction($name) !== null;
    }

    /**
     * The actions shown to the user: visible, and allowed by their can().
     *
     * @return array<string, Action>
     */
    public function getAvailableActions(): array
    {
        $user = $this->getUser();

        return array_filter($this->getActions(), fn (Action $action) => $action->isAvailableFor($user));
    }

    public function hasAvailableActions(): bool
    {
        return $this->getAvailableActions() !== [];
    }

    /*
    |--------------------------------------------------------------------------
    | Execution
    |--------------------------------------------------------------------------
    */

    /**
     * Validate a bulk action request and run the action, during the request
     * or on the queue. Throws a ValidationException for a missing action,
     * selection or invalid form, and an AuthorizationException when the
     * action's can() fails.
     */
    public function execute(?Request $request = null): ActionResult
    {
        return app(Executor::class)->execute($this, $request ?? request());
    }

    /**
     * Execute and respond: back to the listing with the result flashed, or
     * the result as JSON for requests that want JSON.
     */
    public function respond(?Request $request = null): Response
    {
        $request ??= request();

        return app(Executor::class)->respond($this, $request, $this->execute($request));
    }

    /**
     * The result of the last action, flashed for the page after it ran.
     */
    public function getResult(): ?ActionResult
    {
        $result = session()->get($this->sessionKey());

        if (! is_array($result)) {
            return null;
        }

        return ActionResult::fromArray($result);
    }

    public function sessionKey(): string
    {
        return "bulk-actions.results.{$this->getName()}";
    }

    /*
    |--------------------------------------------------------------------------
    | Closures
    |--------------------------------------------------------------------------
    */

    protected function closureArgumentsByName(): array
    {
        return [
            'bulkActions' => fn () => $this,
            'model' => fn () => $this->getModel(),
            'user' => fn () => $this->getUser(),
            'request' => fn () => request(),
        ];
    }

    protected function closureArgumentsByType(): array
    {
        return [
            self::class => fn () => $this,
            Request::class => fn () => request(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    */

    /**
     * Strings the UI shows, translated for the current locale, so React needs
     * no locale setup.
     *
     * @return array<string, string>
     */
    public function getStrings(): array
    {
        $keys = [
            'actions', 'apply', 'choose_action', 'selected', 'none_selected', 'select_all_matching',
            'all_selected', 'all_on_page_selected', 'clear_selection', 'cancel', 'confirm',
            'running', 'queued', 'progress', 'cancel_run', 'dismiss',
        ];

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => __("bulk-actions::strings.ui.{$key}")])
            ->all();
    }

    /**
     * Everything the UI needs: the available actions, the endpoint, the
     * selection name, "select all matching" and the last result.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'url' => $this->getUrl(),
            'actions' => array_values(array_map(fn (Action $action) => $action->toArray(), $this->getAvailableActions())),
            'total' => $this->getTotal(),
            'pageCount' => $this->getPageCount(),
            'filters' => (object) $this->getFilterParameters(),
            'result' => $this->getResult()?->toArray(),
            'old' => [
                'action' => old('action'),
                'all' => (bool) old('all'),
                'ids' => array_values((array) old($this->getName(), [])),
            ],
            'pollInterval' => (int) config('bulk-actions.poll_interval', 2000),
            'strings' => $this->getStrings(),
        ];
    }

    /**
     * Render for the configured stack: the Blade view, or the array for an
     * Inertia prop.
     */
    public function render(?string $views = null): mixed
    {
        $views ??= config('bulk-actions.views', 'blade');

        if ($views === 'inertia' || $views === 'json') {
            return $this->toArray();
        }

        return view('bulk-actions::actions', ['bulkActions' => $this]);
    }

    public function toHtml(): string
    {
        return view('bulk-actions::actions', ['bulkActions' => $this])->render();
    }

    public function __toString(): string
    {
        return $this->toHtml();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
