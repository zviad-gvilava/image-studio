<?php

namespace App\Billing;

use Brick\Math\RoundingMode;
use Illuminate\Container\Attributes\Config;

/**
 * Turns what the provider charges us into what we charge the user.
 */
final readonly class Markup
{
    public function __construct(
        #[Config('billing.markup_percent')]
        private int $percent,
    ) {}

    /**
     * Exact at today's rates. If a future rate ever leaves a fraction of a nanodollar,
     * it is rounded up so we never charge less than cost plus markup.
     */
    public function apply(Money $providerCost): Money
    {
        $price = $providerCost->toDecimal()
            ->multipliedBy(100 + $this->percent)
            ->dividedBy(100, Money::SCALE, RoundingMode::Up);

        return Money::of($price);
    }
}
