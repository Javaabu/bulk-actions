<?php

namespace Javaabu\BulkActions\Views\Components;

use Closure;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Javaabu\BulkActions\BulkActions;
use Javaabu\Manifest\Facades\Manifest;

/**
 * `<x-bulk-actions::actions :actions="$bulkActions" :paginator="$articles" />`: the
 * action select, Apply button, selected count, "select all matching", the
 * confirmation and form dialogs and the result. Goes in the `bulk-form` slot
 * of javaabu/forms' `<x-forms::table>`.
 */
class Actions extends Component
{
    public function __construct(
        public BulkActions $actions,
        public ?Paginator $paginator = null,
        public ?int $total = null,
        public string $framework = '',
    ) {
        if ($paginator !== null) {
            $actions->paginator($paginator);
        }

        if ($total !== null) {
            $actions->total($total);
        }
    }

    public function render(): View|Closure|string
    {
        if (config('bulk-actions.assets', true)) {
            Manifest::require('bulk-actions::bulk-actions');
        }

        return view('bulk-actions::actions', [
            'bulkActions' => $this->actions,
            'framework' => $this->framework ?: config('forms.framework', 'onu'),
        ]);
    }
}
