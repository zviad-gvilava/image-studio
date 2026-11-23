<?php

namespace App\Pixelforge;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;

/**
 * The body of POST /v1/generations.
 */
final readonly class GenerationParameters
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    public function __construct(
        public string $prompt,
        public ImageSize $size,
        public ImageQuality $quality,
        public array $referenceImages = [],
        public array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'quality' => $this->quality->value,
            'reference_images' => $this->referenceImages,
            'metadata' => $this->metadata,
        ], fn (mixed $value): bool => $value !== []);
    }
}
