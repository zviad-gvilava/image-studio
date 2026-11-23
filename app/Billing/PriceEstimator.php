<?php

namespace App\Billing;

use App\Generations\GenerationOptions;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\Pricing;
use App\Pixelforge\TokenUsage;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Prices a generation before it is submitted, from Pixelforge's current published pricing.
 */
final readonly class PriceEstimator
{
    public function __construct(
        private PixelforgeClient $pixelforge,
        private Markup $markup,
    ) {}

    public function estimate(GenerationOptions $options): PriceEstimate
    {
        $pricing = $this->pixelforge->pricing();
        $textTokens = $this->estimatedTextTokens($options->promptCharacters(), $pricing);
        $imageTokens = count($options->referenceImages) * $pricing->tokensPerReferenceImage;
        $outputTokens = $pricing->outputTokens($options->quality, $options->size);

        $minUsage = new TokenUsage(
            inputTextTokens: $this->scaled($textTokens, BigDecimal::one()->minus($pricing->textTokenVariance), RoundingMode::Floor),
            inputImageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: $pricing->minReasoningTokens($options->quality),
        );

        $maxUsage = new TokenUsage(
            inputTextTokens: $this->scaled($textTokens, BigDecimal::one()->plus($pricing->textTokenVariance), RoundingMode::Ceiling),
            inputImageTokens: $imageTokens,
            outputTokens: $outputTokens,
            reasoningTokens: $pricing->maxReasoningTokens($options->quality),
        );

        $minCost = $pricing->rates->costOf($minUsage);
        $maxCost = $pricing->rates->costOf($maxUsage);

        return new PriceEstimate(
            minUsage: $minUsage,
            maxUsage: $maxUsage,
            minCost: $minCost,
            maxCost: $maxCost,
            minPrice: $this->markup->apply($minCost),
            maxPrice: $this->markup->apply($maxCost),
        );
    }

    /**
     * Pixelforge's rule of thumb: ceil(characters / characters per token).
     */
    private function estimatedTextTokens(int $characters, Pricing $pricing): int
    {
        return intdiv($characters + $pricing->charactersPerTextToken - 1, $pricing->charactersPerTextToken);
    }

    private function scaled(int $tokens, BigDecimal $factor, RoundingMode $rounding): int
    {
        return $factor->multipliedBy($tokens)->toScale(0, $rounding)->toInt();
    }
}
