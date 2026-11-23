<?php

namespace App\Reports;

use App\Billing\Money;
use Brick\Math\RoundingMode;

/**
 * Totals for one slice of the profit report: a user's day, a whole day, a user, or everything.
 */
final readonly class ProfitLine
{
    public function __construct(
        public int $generations,
        public Money $charged,
        public Money $providerCost,
    ) {}

    public static function empty(): self
    {
        return new self(0, Money::zero(), Money::zero());
    }

    public function plus(self $other): self
    {
        return new self(
            $this->generations + $other->generations,
            $this->charged->plus($other->charged),
            $this->providerCost->plus($other->providerCost),
        );
    }

    public function profit(): Money
    {
        return $this->charged->minus($this->providerCost);
    }

    /**
     * Profit as a share of what users were charged, e.g. "20.0". Null when nothing was charged.
     */
    public function marginPercent(): ?string
    {
        if ($this->charged->isZero()) {
            return null;
        }

        return $this->profit()->toDecimal()
            ->multipliedBy(100)
            ->dividedBy($this->charged->toDecimal(), 1, RoundingMode::HalfUp)
            ->toString();
    }

    /**
     * @return array{generations: int, charged: array{exact: string, formatted: string}, provider_cost: array{exact: string, formatted: string}, profit: array{exact: string, formatted: string}, margin_percent: string|null}
     */
    public function toArray(): array
    {
        return [
            'generations' => $this->generations,
            'charged' => $this->charged->display(),
            'provider_cost' => $this->providerCost->display(),
            'profit' => $this->profit()->display(),
            'margin_percent' => $this->marginPercent(),
        ];
    }
}
