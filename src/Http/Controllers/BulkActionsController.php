<?php

namespace Javaabu\BulkActions\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Javaabu\BulkActions\BulkActions;
use Javaabu\BulkActions\Routing\BulkActionRoutes;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the bulk actions of the class the route was registered for with
 * Route::bulkActions(). Extend it and set it in
 * config('bulk-actions.controllers.bulk_actions') to change how it responds.
 */
class BulkActionsController extends Controller
{
    public function store(Request $request): Response
    {
        return $this->bulkActions($request)->respond($request);
    }

    protected function bulkActions(Request $request): BulkActions
    {
        $class = $request->route()?->getAction(BulkActionRoutes::CLASS_ACTION);

        if (! is_string($class) || ! is_a($class, BulkActions::class, true)) {
            throw new RuntimeException('This route has no BulkActions class. Register it with Route::bulkActions($uri, ArticleBulkActions::class).');
        }

        return $class::make();
    }
}
