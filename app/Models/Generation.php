<?php

namespace App\Models;

use App\Billing\Money;
use App\Casts\MoneyCast;
use App\Casts\RatesCast;
use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Pixelforge\GenerationParameters;
use App\Pixelforge\GenerationSnapshot;
use App\Pixelforge\Rates;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $provider_id
 * @property GenerationStatus $status
 * @property string $prompt
 * @property ImageSize $size
 * @property ImageQuality $quality
 * @property list<string> $reference_images
 * @property Money $estimated_min_price
 * @property Money $estimated_max_price
 * @property Rates|null $rates
 * @property int|null $input_text_tokens
 * @property int|null $input_image_tokens
 * @property int|null $output_tokens
 * @property int|null $reasoning_tokens
 * @property Money|null $provider_cost
 * @property Money|null $price
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property string|null $error_code
 * @property int $submission_attempts
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'status',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'estimated_min_price',
    'estimated_max_price',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    /**
     * Matches a Pixelforge generation to ours: by Pixelforge's id, or, when a webhook beats the
     * create response back to us, by the id we put in its metadata.
     */
    public static function findForSnapshot(GenerationSnapshot $snapshot): ?self
    {
        $byProviderId = self::query()->where('provider_id', $snapshot->id)->first();

        if ($byProviderId !== null || $snapshot->generationId === null) {
            return $byProviderId;
        }

        return self::query()->whereKey($snapshot->generationId)->whereNull('provider_id')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pixelforge has the final word on anything it accepted. That includes a generation we gave up on
     * before hearing back: Pixelforge may have accepted it after all, and then it bills for it.
     */
    public function acceptsProviderStatus(GenerationStatus $status): bool
    {
        if ($this->status === GenerationStatus::Failed && $this->provider_id === null) {
            return true;
        }

        return $this->status->canAdvanceTo($status);
    }

    public function toParameters(): GenerationParameters
    {
        return new GenerationParameters(
            prompt: $this->prompt,
            size: $this->size,
            quality: $this->quality,
            referenceImages: $this->reference_images,
            metadata: ['generation_id' => $this->id],
        );
    }

    public function imageIsAvailable(): bool
    {
        return $this->image_url !== null && ($this->image_url_expires_at?->isFuture() ?? true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inProgress(Builder $query): void
    {
        $query->whereIn('status', GenerationStatus::inProgress());
    }

    /**
     * Accepted by Pixelforge and not finished yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function awaitingProvider(Builder $query): void
    {
        $query->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])->whereNotNull('provider_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'size' => ImageSize::class,
            'quality' => ImageQuality::class,
            'reference_images' => 'array',
            'estimated_min_price' => MoneyCast::class,
            'estimated_max_price' => MoneyCast::class,
            'rates' => RatesCast::class,
            'provider_cost' => MoneyCast::class,
            'price' => MoneyCast::class,
            'image_url_expires_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
