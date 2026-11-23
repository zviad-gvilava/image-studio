<?php

namespace App\Generations;

use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Pixelforge\GenerationSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandlePixelforgeEvent
{
    public function __construct(
        private GenerationLifecycle $lifecycle,
    ) {}

    /**
     * Recording the event and applying it share one transaction: a redelivered event is
     * ignored, and an event that failed to apply is rolled back so its retry is processed.
     *
     * @param  array<string, mixed>  $event
     */
    public function __invoke(string $eventId, string $type, array $event): void
    {
        $snapshot = $this->snapshot($type, $event);

        DB::transaction(function () use ($eventId, $type, $event, $snapshot): void {
            if (! WebhookEvent::recordOnce($eventId, $type, $snapshot?->id, $event) || $snapshot === null) {
                return;
            }

            $generation = Generation::findForSnapshot($snapshot);

            if ($generation === null) {
                Log::warning('Pixelforge sent an event for a generation we do not have.', [
                    'event_id' => $eventId,
                    'provider_id' => $snapshot->id,
                ]);

                return;
            }

            $this->lifecycle->sync($generation, $snapshot);
        });
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function snapshot(string $type, array $event): ?GenerationSnapshot
    {
        $data = $event['data'] ?? null;
        $object = is_array($data) ? ($data['object'] ?? null) : null;

        return str_starts_with($type, 'generation.') && is_array($object)
            ? GenerationSnapshot::fromArray($object)
            : null;
    }
}
