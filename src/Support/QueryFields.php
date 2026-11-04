<?php

namespace Javaabu\BulkActions\Support;

/**
 * Turns nested query parameters into hidden field names and values:
 * `['status' => ['draft', 'published']]` under `filters` becomes
 * `filters[status][0]=draft`, `filters[status][1]=published`.
 */
class QueryFields
{
    /**
     * @param  array<array-key, mixed>  $parameters
     * @return array<int, array{name: string, value: string}>
     */
    public static function flatten(array $parameters, string $prefix): array
    {
        $fields = [];

        foreach ($parameters as $key => $value) {
            $name = "{$prefix}[{$key}]";

            if (is_array($value)) {
                array_push($fields, ...static::flatten($value, $name));

                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $fields[] = ['name' => $name, 'value' => (string) $value];
        }

        return $fields;
    }
}
