<?php

namespace App\Pixelforge;

use App\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use UnexpectedValueException;

/**
 * A Pixelforge generation object as it was at one moment: from the create response,
 * a webhook, or a poll.
 */
final readonly class GenerationSnapshot
{
    public function __construct(
        public string $id,
        public GenerationStatus $status,
        public Rates $rates,
        public ?TokenUsage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public ?string $errorCode,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
        /** Our own generation id, echoed back from the metadata we sent. */
        public ?string $generationId,
    ) {}

    /**
     * @param  array<array-key, mixed>  $generation
     */
    public static function fromArray(array $generation): self
    {
        $id = $generation['id'] ?? null;
        $status = GenerationStatus::tryFrom(is_string($generation['status'] ?? null) ? $generation['status'] : '');
        $rates = $generation['rates'] ?? null;

        if (! is_string($id) || $status === null || ! is_array($rates)) {
            throw new UnexpectedValueException('Pixelforge sent a malformed generation object.');
        }

        $usage = $generation['usage'] ?? null;
        $error = $generation['error'] ?? null;
        $metadata = $generation['metadata'] ?? null;

        return new self(
            id: $id,
            status: $status,
            rates: Rates::fromArray($rates),
            usage: is_array($usage) ? TokenUsage::fromArray($usage) : null,
            imageUrl: self::string($generation['image_url'] ?? null),
            imageUrlExpiresAt: self::timestamp($generation['image_url_expires_at'] ?? null),
            errorCode: is_array($error) ? self::string($error['code'] ?? null) : null,
            startedAt: self::timestamp($generation['started_at'] ?? null),
            completedAt: self::timestamp($generation['completed_at'] ?? null),
            generationId: is_array($metadata) ? self::string($metadata['generation_id'] ?? null) : null,
        );
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function timestamp(mixed $value): ?CarbonImmutable
    {
        $value = self::string($value);

        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }
}
