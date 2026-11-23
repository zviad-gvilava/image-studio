<?php

namespace App\Enums;

enum GenerationStatus: string
{
    /** Saved by us, not yet accepted by Pixelforge (e.g. waiting out a rate limit). */
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Pending, self::Queued, self::Processing];
    }

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    /**
     * Status only ever moves forward, so late or repeated events can't undo progress.
     */
    public function canAdvanceTo(self $next): bool
    {
        return ! $this->isFinal() && $next->stage() > $this->stage();
    }

    private function stage(): int
    {
        return match ($this) {
            self::Pending => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
