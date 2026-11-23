<?php

namespace Database\Factories;

use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => fake()->sentence(),
            'size' => ImageSize::Square,
            'quality' => ImageQuality::Medium,
            'reference_images' => [],
            'estimated_min_price' => Money::of('0.0605'),
            'estimated_max_price' => Money::of('0.0862'),
        ];
    }

    /**
     * Accepted by Pixelforge under the given id.
     */
    public function accepted(string $providerId, GenerationStatus $status = GenerationStatus::Queued): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => $providerId,
            'status' => $status,
        ]);
    }
}
