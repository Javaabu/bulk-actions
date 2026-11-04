<?php

namespace Javaabu\BulkActions\Execution;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Which records a bulk action runs on: the ids the user checked, or every
 * record matching the filters the listing was showing.
 *
 * @implements Arrayable<string, mixed>
 */
class Selection implements Arrayable
{
    /**
     * @param  array<int, int|string>  $ids
     * @param  array<string, mixed>  $filters  the listing's query string parameters
     */
    public function __construct(
        public array $ids = [],
        public bool $all = false,
        public array $filters = [],
    ) {}

    /**
     * Read the selection from a bulk action request: the checked ids as
     * `{name}[]`, or `all=1` with the listing's query as `filters[...]`.
     */
    public static function fromRequest(Request $request, string $name): self
    {
        $ids = Arr::wrap($request->input($name, []));

        $ids = array_values(array_unique(array_filter(
            $ids,
            fn ($id) => (is_string($id) || is_int($id)) && $id !== '',
        )));

        $filters = $request->input('filters', []);

        return new self(
            ids: $ids,
            all: $request->boolean('all'),
            filters: is_array($filters) ? $filters : [],
        );
    }

    public function isEmpty(): bool
    {
        if ($this->all) {
            return false;
        }

        return $this->ids === [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ids: $data['ids'] ?? [],
            all: (bool) ($data['all'] ?? false),
            filters: $data['filters'] ?? [],
        );
    }

    /**
     * @return array{ids: array<int, int|string>, all: bool, filters: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'ids' => $this->ids,
            'all' => $this->all,
            'filters' => $this->filters,
        ];
    }
}
