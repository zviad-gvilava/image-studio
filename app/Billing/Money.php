<?php

namespace App\Billing;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * An exact amount of US dollars, held as a whole number of nanodollars ($0.000000001),
 * the precision Pixelforge reports costs in. Floats never touch money.
 */
final readonly class Money
{
    public const int SCALE = 9;

    private function __construct(public int $nanos) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofNanos(int $nanos): self
    {
        return new self($nanos);
    }

    /**
     * Throws when the amount has more than nine decimal places, rather than silently rounding it.
     */
    public static function of(BigDecimal|int|string $dollars): self
    {
        return new self(BigDecimal::of($dollars)->toScale(self::SCALE)->getUnscaledValue()->toInt());
    }

    public function plus(self $other): self
    {
        return new self($this->nanos + $other->nanos);
    }

    public function minus(self $other): self
    {
        return new self($this->nanos - $other->nanos);
    }

    public function negated(): self
    {
        return new self(-$this->nanos);
    }

    public function isLessThan(self $other): bool
    {
        return $this->nanos < $other->nanos;
    }

    public function isZero(): bool
    {
        return $this->nanos === 0;
    }

    public function toDecimal(): BigDecimal
    {
        return BigDecimal::ofUnscaledValue($this->nanos, self::SCALE);
    }

    /**
     * The exact amount, always with nine decimals, e.g. "0.046125000".
     */
    public function toString(): string
    {
        return $this->toDecimal()->toString();
    }

    /**
     * A rounded amount for people to read: cents always, fractions of a cent only when present,
     * e.g. "$10.00", "$0.0577" or "-$1,250.50".
     *
     * @param  int<0, max>  $maxDecimals
     * @param  int<0, max>  $minDecimals
     */
    public function format(int $maxDecimals = 4, int $minDecimals = 2): string
    {
        $rounded = $this->toDecimal()->abs()->toScale($maxDecimals, RoundingMode::HalfUp);
        [$whole, $fraction] = array_pad(explode('.', $rounded->toString()), 2, '');
        $fraction = str_pad(rtrim($fraction, '0'), min($minDecimals, $maxDecimals), '0');
        $sign = $this->nanos < 0 && ! $rounded->isZero() ? '-' : '';

        return $sign.'$'.number_format((int) $whole).($fraction === '' ? '' : ".{$fraction}");
    }

    /**
     * @return array{exact: string, formatted: string}
     */
    public function display(): array
    {
        return [
            'exact' => $this->toString(),
            'formatted' => $this->format(),
        ];
    }
}
