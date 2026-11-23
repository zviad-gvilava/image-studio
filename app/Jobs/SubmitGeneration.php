<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Generations\GenerationLifecycle;
use App\Models\Generation;
use App\Pixelforge\ErrorCode;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hands a generation to Pixelforge, waiting out rate limits, concurrency limits and overloads.
 * Every attempt sends the same Idempotency-Key, so a retry can never create a second generation.
 */
class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /**
     * Waiting for a free concurrency slot is normal and can take minutes, so it gets the most
     * patience: each wait counts as an attempt, capping the wait at about 10 minutes.
     */
    public int $tries = 12;

    /**
     * Overloads, rate limits and network errors get a few tries (about 30 seconds), then fail for free.
     */
    private const int ATTEMPTS_FOR_ERRORS = 5;

    /**
     * Unexpected errors (as opposed to Pixelforge saying "not now") get a few spaced-out retries, then the generation fails for free.
     */
    public int $maxExceptions = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    /**
     * Seconds to wait before each retry, unless Pixelforge asks for longer.
     */
    private const array BACKOFF_SECONDS = [2, 4, 8, 15, 30, 45, 60, 60, 90, 120, 180];

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $pixelforge, GenerationLifecycle $lifecycle): void
    {
        if ($this->generation->status !== GenerationStatus::Pending) {
            return;
        }

        try {
            $snapshot = $pixelforge->createGeneration($this->generation->toParameters(), idempotencyKey: $this->generation->id);
        } catch (PixelforgeException $exception) {
            $this->handleRejection($exception, $lifecycle);

            return;
        }

        $lifecycle->sync($this->generation, $snapshot);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationLifecycle::class)->reject($this->generation, ErrorCode::Unknown);
    }

    private function handleRejection(PixelforgeException $exception, GenerationLifecycle $lifecycle): void
    {
        if ($this->shouldRetry($exception)) {
            $lifecycle->noteRetry($this->generation, $exception->errorCode, $this->attempts());
            $this->release($this->retryDelay($exception));

            return;
        }

        if (! $exception->isTemporary()) {
            Log::warning('Pixelforge rejected a generation.', [
                'generation_id' => $this->generation->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $lifecycle->reject($this->generation, $exception->errorCode);
    }

    private function shouldRetry(PixelforgeException $exception): bool
    {
        $attemptLimit = $exception->errorCode === ErrorCode::ConcurrencyLimitExceeded
            ? $this->tries
            : self::ATTEMPTS_FOR_ERRORS;

        return $exception->isTemporary() && $this->attempts() < $attemptLimit;
    }

    /**
     * Backs off further each time, respects Retry-After, and adds jitter so waiting jobs don't retry in lockstep.
     */
    private function retryDelay(PixelforgeException $exception): int
    {
        $backoff = self::BACKOFF_SECONDS[min($this->attempts(), count(self::BACKOFF_SECONDS)) - 1];

        return max($backoff, $exception->retryAfterSeconds ?? 0) + random_int(0, 2);
    }
}
