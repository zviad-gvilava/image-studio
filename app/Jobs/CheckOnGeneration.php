<?php

namespace App\Jobs;

use App\Generations\GenerationLifecycle;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Webhook delivery isn't guaranteed, so every accepted generation is checked on a little later.
 * If a webhook has settled it by then, the check does nothing. Otherwise Pixelforge is asked
 * directly, and the check repeats with growing gaps until the generation finishes. Nothing
 * runs while nothing is in flight, so the app and its database can scale to zero.
 */
class CheckOnGeneration implements ShouldQueue
{
    use Queueable;

    /**
     * Seconds before each check: the first gives the webhook time to arrive, the rest back off (about an hour in all).
     *
     * @var list<int>
     */
    public const array DELAYS_SECONDS = [60, 120, 300, 600, 900, 900, 900];

    /**
     * Each scheduled check counts as an attempt.
     */
    public int $tries = 7;

    /**
     * @var list<int>
     */
    public array $backoff = [60];

    public function __construct(public Generation $generation) {}

    public static function scheduleFor(Generation $generation): void
    {
        self::dispatch($generation)->delay(self::DELAYS_SECONDS[0]);
    }

    public function handle(PixelforgeClient $pixelforge, GenerationLifecycle $lifecycle): void
    {
        $providerId = $this->generation->provider_id;

        if ($providerId === null || $this->generation->status->isFinal()) {
            return;
        }

        try {
            $lifecycle->sync($this->generation, $pixelforge->generation($providerId));
        } catch (PixelforgeException $exception) {
            if (! $exception->isTemporary()) {
                Log::warning('Stopped checking on a generation Pixelforge could not return.', [
                    'generation_id' => $this->generation->id,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }
        }

        $nextDelay = self::DELAYS_SECONDS[$this->attempts()] ?? null;

        if ($nextDelay !== null && ! $this->generation->refresh()->status->isFinal()) {
            $this->release($nextDelay);
        }
    }
}
