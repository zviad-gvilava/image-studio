<?php

namespace App\Pixelforge;

use App\Billing\Money;
use UnexpectedValueException;

/**
 * One UTC day of GET /v1/usage: what Pixelforge billed this API key for generations completed that day.
 */
final readonly class UsageDay
{
    public function __construct(
        public string $date,
        public int $generations,
        public Money $cost,
    ) {}

    /**
     * @param  array<array-key, mixed>  $day
     */
    public static function fromArray(array $day): self
    {
        $date = $day['date'] ?? null;
        $generations = $day['generations'] ?? null;
        $cost = $day['cost_usd'] ?? null;

        if (! is_string($date) || ! is_int($generations) || ! is_string($cost)) {
            throw new UnexpectedValueException('Pixelforge sent a malformed usage day.');
        }

        return new self($date, $generations, Money::of($cost));
    }
}
