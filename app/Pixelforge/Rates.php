<?php

namespace App\Pixelforge;

use App\Billing\Money;
use Brick\Math\BigDecimal;
use UnexpectedValueException;

/**
 * Prices in US dollars per million tokens. Each generation is billed at the rates
 * returned with it, which are fixed when Pixelforge accepts it.
 */
final readonly class Rates
{
    private const int TOKENS_PER_PRICE_UNIT = 1_000_000;

    public function __construct(
        public BigDecimal $inputTextTokens,
        public BigDecimal $inputImageTokens,
        public BigDecimal $outputTokens,
        public BigDecimal $reasoningTokens,
    ) {}

    /**
     * @param  array<array-key, mixed>  $rates
     */
    public static function fromArray(array $rates): self
    {
        return new self(
            inputTextTokens: self::rate($rates, 'input_text_tokens'),
            inputImageTokens: self::rate($rates, 'input_image_tokens'),
            outputTokens: self::rate($rates, 'output_tokens'),
            reasoningTokens: self::rate($rates, 'reasoning_tokens'),
        );
    }

    /**
     * @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputTextTokens->toString(),
            'input_image_tokens' => $this->inputImageTokens->toString(),
            'output_tokens' => $this->outputTokens->toString(),
            'reasoning_tokens' => $this->reasoningTokens->toString(),
        ];
    }

    public function costOf(TokenUsage $usage): Money
    {
        $dollarsPerMillionTokens = $this->inputTextTokens->multipliedBy($usage->inputTextTokens)
            ->plus($this->inputImageTokens->multipliedBy($usage->inputImageTokens))
            ->plus($this->outputTokens->multipliedBy($usage->outputTokens))
            ->plus($this->reasoningTokens->multipliedBy($usage->reasoningTokens));

        return Money::of($dollarsPerMillionTokens->dividedByExact(self::TOKENS_PER_PRICE_UNIT));
    }

    /**
     * @param  array<array-key, mixed>  $rates
     */
    private static function rate(array $rates, string $key): BigDecimal
    {
        $rate = $rates[$key] ?? null;

        if (! is_string($rate) && ! is_int($rate)) {
            throw new UnexpectedValueException("Pixelforge rates are missing [{$key}].");
        }

        return BigDecimal::of($rate);
    }
}
