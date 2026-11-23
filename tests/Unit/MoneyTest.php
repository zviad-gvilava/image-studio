<?php

namespace Tests\Unit;

use App\Billing\Markup;
use App\Billing\Money;
use Brick\Math\Exception\RoundingNecessaryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_keeps_exact_nanodollar_amounts(): void
    {
        $money = Money::of('0.046125000');

        $this->assertSame(46_125_000, $money->nanos);
        $this->assertSame('0.046125000', $money->toString());
    }

    public function test_it_refuses_to_silently_round_amounts_finer_than_a_nanodollar(): void
    {
        $this->expectException(RoundingNecessaryException::class);

        Money::of('0.0000000001');
    }

    public function test_arithmetic_is_exact(): void
    {
        $total = Money::of('0.1')->plus(Money::of('0.2'));

        $this->assertSame('0.300000000', $total->toString());
        $this->assertSame('-0.100000000', Money::of('0.2')->minus(Money::of('0.3'))->toString());
        $this->assertSame('-0.300000000', $total->negated()->toString());
    }

    #[DataProvider('displayAmounts')]
    public function test_it_formats_amounts_for_people(string $amount, string $expected): void
    {
        $this->assertSame($expected, Money::of($amount)->format());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function displayAmounts(): array
    {
        return [
            'whole dollars' => ['10', '$10.00'],
            'cents' => ['0.05', '$0.05'],
            'fractions of a cent' => ['0.057656250', '$0.0577'],
            'thousands' => ['1250.5', '$1,250.50'],
            'negative' => ['-0.0712', '-$0.0712'],
            'rounds to zero without a sign' => ['-0.00001', '$0.00'],
        ];
    }

    public function test_markup_adds_exactly_twenty_five_percent(): void
    {
        $markup = new Markup(25);

        $this->assertSame('0.381887500', $markup->apply(Money::of('0.305510000'))->toString());
        $this->assertSame('0.467343750', $markup->apply(Money::of('0.373875000'))->toString());
        $this->assertTrue($markup->apply(Money::zero())->isZero());
    }

    public function test_markup_rounds_a_sub_nanodollar_remainder_up_so_we_never_undercharge(): void
    {
        $this->assertSame(2, (new Markup(25))->apply(Money::ofNanos(1))->nanos);
    }
}
