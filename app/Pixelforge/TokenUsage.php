<?php

namespace App\Pixelforge;

use UnexpectedValueException;

final readonly class TokenUsage
{
    public function __construct(
        public int $inputTextTokens,
        public int $inputImageTokens,
        public int $outputTokens,
        public int $reasoningTokens,
    ) {}

    /**
     * @param  array<array-key, mixed>  $usage
     */
    public static function fromArray(array $usage): self
    {
        return new self(
            inputTextTokens: self::count($usage, 'input_text_tokens'),
            inputImageTokens: self::count($usage, 'input_image_tokens'),
            outputTokens: self::count($usage, 'output_tokens'),
            reasoningTokens: self::count($usage, 'reasoning_tokens'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $usage
     */
    private static function count(array $usage, string $key): int
    {
        $count = $usage[$key] ?? null;

        if (! is_int($count) || $count < 0) {
            throw new UnexpectedValueException("Pixelforge usage is missing a valid [{$key}].");
        }

        return $count;
    }
}
