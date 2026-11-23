<?php

namespace App\Generations;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * What a user asked for, before it is priced or sent anywhere.
 */
final readonly class GenerationOptions
{
    public const int MAX_PROMPT_LENGTH = 4000;

    public const int MAX_REFERENCE_IMAGES = 4;

    /**
     * @param  list<string>  $referenceImages
     */
    public function __construct(
        public string $prompt,
        public ImageSize $size,
        public ImageQuality $quality,
        public array $referenceImages = [],
    ) {}

    /**
     * Pixelforge counts Unicode characters, not bytes.
     */
    public function promptCharacters(): int
    {
        return mb_strlen($this->prompt);
    }
}
