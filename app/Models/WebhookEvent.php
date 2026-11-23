<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * A Pixelforge webhook event we have acted on, kept so redeliveries can be recognised.
 *
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property string|null $provider_generation_id
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $received_at
 */
#[WithoutTimestamps]
class WebhookEvent extends Model
{
    /**
     * Records the event unless it was recorded before. Returns false for a redelivery.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function recordOnce(string $eventId, string $type, ?string $providerGenerationId, array $payload): bool
    {
        return self::query()->insertOrIgnore([
            'event_id' => $eventId,
            'type' => $type,
            'provider_generation_id' => $providerGenerationId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'received_at' => now(),
        ]) === 1;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }
}
