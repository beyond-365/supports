<?php

use Beyond\Supports\Collection;

if (!function_exists('collect')) {
    /**
     * Create a collection from the given value.
     * @param array $value
     * @return Collection
     */
    function collect(array $value = []): Collection
    {
        return new Collection($value);
    }
}
