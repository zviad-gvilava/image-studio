<?php

namespace App\Casts;

use App\Pixelforge\Rates;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores Pixelforge rates as JSON, keeping their exact decimal strings.
 *
 * @implements CastsAttributes<Rates|null, Rates|null>
 */
final class RatesCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Rates
    {
        if (! is_string($value)) {
            return null;
        }

        $rates = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

        return Rates::fromArray(is_array($rates) ? $rates : []);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value->toArray(), JSON_THROW_ON_ERROR);
    }
}
