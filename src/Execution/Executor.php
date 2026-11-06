<?php

namespace Javaabu\BulkActions\Execution;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Javaabu\BulkActions\Actions\Action;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Events\BulkActionCompleted;
use Javaabu\BulkActions\Events\BulkActionQueued;
use Javaabu\BulkActions\Exceptions\BulkActionsException;
use Javaabu\BulkActions\Jobs\RunBulkAction;
use Javaabu\BulkActions\Models\BulkActionRun;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles a bulk action request: checks the action, the selection and the
 * form, then runs the action now or queues it.
 */
class Executor
{
    public function execute(BulkActions $bulk_actions, Request $request): ActionResult
    {
        $user = $bulk_actions->getUser();

        $action = $this->resolveAction($bulk_actions, $request);

        if (! $action->isAuthorized($user)) {
            throw new AuthorizationException(__('bulk-actions::strings.unauthorized'));
        }

        $selection = Selection::fromRequest($request, $bulk_actions->getName());

        if ($selection->isEmpty()) {
            throw ValidationException::withMessages([
                $bulk_actions->getName() => __('bulk-actions::strings.validation.selection'),
            ]);
        }

        $data = $action->getForm()?->validate($request) ?? [];

        $runner = new Runner($bulk_actions, $action, $selection, $data, $user);

        $total = $runner->count();

        if ($action->shouldQueue($total)) {
            return $this->queue($bulk_actions, $action, $selection, $data, $total);
        }

        $result = $runner->run($total);

        BulkActionCompleted::dispatch($bulk_actions::class, $action->getName(), $result, $selection, $data, $user);

        return $result;
    }

    protected function resolveAction(BulkActions $bulk_actions, Request $request): Action
    {
        $name = $request->input('action');

        $action = is_string($name) ? $bulk_actions->getAction($name) : null;

        if ($action === null) {
            throw ValidationException::withMessages([
                'action' => __('bulk-actions::strings.validation.action'),
            ]);
        }

        return $action;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function queue(BulkActions $bulk_actions, Action $action, Selection $selection, array $data, int $total): ActionResult
    {
        if ($bulk_actions->isInline()) {
            throw BulkActionsException::cannotQueueInline($action->getName());
        }

        $user = $bulk_actions->getUser();

        /** @var class-string<BulkActionRun> $model */
        $model = config('bulk-actions.models.run', BulkActionRun::class);

        $run = new $model;

        $run->forceFill([
            'bulk_actions' => $bulk_actions::class,
            'name' => $bulk_actions->getName(),
            'action' => $action->getName(),
            'selection' => $selection->toArray(),
            'data' => $data ?: null,
            'context' => $this->context($bulk_actions),
            'total' => $total,
            'locale' => app()->getLocale(),
            'message' => trans_choice('bulk-actions::strings.queued', $total, ['count' => $total, 'action' => $action->getLabel()]),
        ]);

        if ($user instanceof Model) {
            $run->user()->associate($user);
        }

        $run->save();

        BulkActionQueued::dispatch($run);

        $job = new RunBulkAction($run);

        $connection = config('bulk-actions.queue.connection');
        $queue = config('bulk-actions.queue.queue');

        dispatch($job->onConnection($connection)->onQueue($queue));

        return $run->fresh()->toResult();
    }

    /**
     * The set's context for the run, checked so the job can rebuild it.
     *
     * @return array<string, mixed>|null
     */
    protected function context(BulkActions $bulk_actions): ?array
    {
        $context = $bulk_actions->context();

        if ($context === []) {
            return null;
        }

        $bulk_actions::assertSerializableContext($context);

        return $context;
    }

    /**
     * The response to a bulk action request: JSON for requests that want
     * JSON (and aren't Inertia visits), otherwise back to the listing with
     * the result flashed.
     */
    public function respond(BulkActions $bulk_actions, Request $request, ActionResult $result): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse($result->toArray(), $result->isQueued() ? 202 : 200);
        }

        $request->session()->flash($bulk_actions->sessionKey(), $result->toArray());

        return new RedirectResponse($this->redirectUrl($request));
    }

    /**
     * Where to go back to: the `redirect` field when it's on this app, else
     * the previous URL.
     */
    protected function redirectUrl(Request $request): string
    {
        $redirect = $request->input('redirect');

        $previous = url()->previous();

        if (! is_string($redirect)) {
            return $previous;
        }

        if ($redirect === '') {
            return $previous;
        }

        if (str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return url($redirect);
        }

        $host = parse_url($redirect, PHP_URL_HOST);

        if ($host !== $request->getHost()) {
            return $previous;
        }

        return $redirect;
    }
}
