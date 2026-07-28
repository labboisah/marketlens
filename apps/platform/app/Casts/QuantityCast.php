<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class QuantityCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): string
    {
        return number_format(((int) $value) / 1000, 3, '.', '');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return (int) round(((float) $value) * 1000);
    }
}
