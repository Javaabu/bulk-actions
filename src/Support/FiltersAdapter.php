<?php

namespace Javaabu\BulkActions\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Applies a listing's filters for "all matching". Works with javaabu/filters
 * (a Filters class or instance), or any object with the same methods:
 * fromQuery(array), applyFilters(Builder) and getFilterParameters(). The
 * filters package is optional, so nothing here references its classes.
 */
class FiltersAdapter
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function apply(mixed $filters, Builder $query, array $parameters): Builder
    {
        $instance = static::instance($filters);

        if (method_exists($instance, 'fromQuery')) {
            $instance = $instance->fromQuery($parameters) ?? $instance;
        }

        if (method_exists($instance, 'applyFilters')) {
            return $instance->applyFilters($query);
        }

        if (method_exists($instance, 'apply')) {
            return $instance->apply($query);
        }

        throw new InvalidArgumentException('Bulk action filters must be a closure or have applyFilters(Builder $query), like javaabu/filters.');
    }

    /**
     * The listing's filter parameters, or null when the filters can't say.
     *
     * @return array<string, mixed>|null
     */
    public static function parameters(mixed $filters): ?array
    {
        if ($filters === null) {
            return null;
        }

        if ($filters instanceof Closure) {
            return null;
        }

        $instance = static::instance($filters);

        if (! method_exists($instance, 'getFilterParameters')) {
            return null;
        }

        return $instance->getFilterParameters();
    }

    protected static function instance(mixed $filters): object
    {
        if (is_object($filters)) {
            return $filters;
        }

        if (is_string($filters) && class_exists($filters)) {
            return method_exists($filters, 'make') ? $filters::make() : app($filters);
        }

        throw new InvalidArgumentException('Bulk action filters must be a closure, a filters class or a filters instance.');
    }
}
