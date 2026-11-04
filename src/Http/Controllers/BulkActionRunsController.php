<?php

namespace Javaabu\BulkActions\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Javaabu\BulkActions\Models\BulkActionRun;
use Symfony\Component\HttpFoundation\Response;

/**
 * The status of a queued bulk action, which the UI polls, and cancelling it.
 * Extend it and set it in config('bulk-actions.controllers.runs') to change it.
 */
class BulkActionRunsController extends Controller
{
    public function show(Request $request, string $bulk_action_run): JsonResponse
    {
        $run = $this->findRun($bulk_action_run);

        Gate::forUser($request->user())->authorize('view', $run);

        return new JsonResponse(['data' => $run->toStatusArray()]);
    }

    public function cancel(Request $request, string $bulk_action_run): Response
    {
        $run = $this->findRun($bulk_action_run);

        Gate::forUser($request->user())->authorize('cancel', $run);

        $run->cancel();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return new JsonResponse(['data' => $run->fresh()->toStatusArray()]);
        }

        return new RedirectResponse(url()->previous());
    }

    protected function findRun(string $uuid): BulkActionRun
    {
        /** @var class-string<BulkActionRun> $model */
        $model = config('bulk-actions.models.run', BulkActionRun::class);

        return $model::query()->where('uuid', $uuid)->firstOrFail();
    }
}
