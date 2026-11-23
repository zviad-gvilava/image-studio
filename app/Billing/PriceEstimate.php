<?php

namespace App\Billing;

use App\Pixelforge\TokenUsage;

/**
 * The cheapest and the most a generation can cost. Text and reasoning token counts are
 * only known once it finishes; everything else is exact up front.
 */
final readonly class PriceEstimate
{
    public function __construct(
        public TokenUsage $minUsage,
        public TokenUsage $maxUsage,
        public Money $minCost,
        public Money $maxCost,
        public Money $minPrice,
        public Money $maxPrice,
    ) {}
}
