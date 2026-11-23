<?php

namespace App\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Brick\Math\BigDecimal;
use UnexpectedValueException;

/**
 * Pixelforge's published rates and token rules, as returned by GET /v1/pricing.
 */
final readonly class Pricing
{
    /**
     * @param  array<array-key, mixed>  $outputTokens  quality => size => tokens
     * @param  array<array-key, mixed>  $reasoningTokens  quality => {min, max}
     */
    public function __construct(
        public Rates $rates,
        public int $charactersPerTextToken,
        public BigDecimal $textTokenVariance,
        public int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    /**
     * @param  array<array-key, mixed>  $pricing
     */
    public static function fromArray(array $pricing): self
    {
        $tokens = self::section($pricing, 'tokens');

        return new self(
            rates: Rates::fromArray(self::section($pricing, 'rates')),
            charactersPerTextToken: self::integer($tokens['characters_per_text_token'] ?? null),
            textTokenVariance: BigDecimal::of(self::scalar($tokens['text_token_variance'] ?? null)),
            tokensPerReferenceImage: self::integer($tokens['per_reference_image'] ?? null),
            outputTokens: self::section($tokens, 'output'),
            reasoningTokens: self::section($tokens, 'reasoning'),
        );
    }

    public function outputTokens(ImageQuality $quality, ImageSize $size): int
    {
        return self::integer(self::section($this->outputTokens, $quality->value)[$size->value] ?? null);
    }

    public function minReasoningTokens(ImageQuality $quality): int
    {
        return self::integer(self::section($this->reasoningTokens, $quality->value)['min'] ?? null);
    }

    public function maxReasoningTokens(ImageQuality $quality): int
    {
        return self::integer(self::section($this->reasoningTokens, $quality->value)['max'] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function section(array $data, string $key): array
    {
        $section = $data[$key] ?? null;

        if (! is_array($section)) {
            throw new UnexpectedValueException("Pixelforge pricing is missing [{$key}].");
        }

        return $section;
    }

    private static function integer(mixed $value): int
    {
        if (! is_int($value)) {
            throw new UnexpectedValueException('Pixelforge pricing contains an invalid token count.');
        }

        return $value;
    }

    private static function scalar(mixed $value): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new UnexpectedValueException('Pixelforge pricing contains an invalid number.');
        }

        return (string) $value;
    }
}
