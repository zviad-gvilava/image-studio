<?php

namespace App\Console\Commands;

use App\Generations\GenerationLifecycle;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Webhook delivery isn't guaranteed, so generations that have gone quiet are polled instead.
 */
class ReconcileGenerations extends Command
{
    protected $signature = 'generations:reconcile
        {--quiet-for=60 : Seconds without an update before a generation is polled}
        {--limit=10 : Most generations to poll in one run, to stay well inside the rate limit}';

    protected $description = 'Poll Pixelforge for generations whose webhooks are overdue';

    public function handle(PixelforgeClient $pixelforge, GenerationLifecycle $lifecycle): int
    {
        $overdue = Generation::query()
            ->awaitingProvider()
            ->where('updated_at', '<', now()->subSeconds((int) $this->option('quiet-for')))
            ->oldest('updated_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($overdue as $generation) {
            try {
                $lifecycle->sync($generation, $pixelforge->generation((string) $generation->provider_id));
            } catch (PixelforgeException $exception) {
                Log::warning('Could not poll a Pixelforge generation.', [
                    'generation_id' => $generation->id,
                    'error' => $exception->getMessage(),
                ]);

                if ($exception->isTemporary()) {
                    break;
                }
            }
        }

        $this->components->info("Polled {$overdue->count()} overdue generation(s).");

        return self::SUCCESS;
    }
}
