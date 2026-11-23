<?php

namespace App\Console\Commands;

use App\Jobs\CheckOnGeneration;
use App\Models\Generation;
use Illuminate\Console\Command;

/**
 * Each generation is already checked on automatically; this sweeps all of them at once,
 * for example after an outage stopped webhooks from arriving.
 */
class ReconcileGenerations extends Command
{
    protected $signature = 'generations:reconcile';

    protected $description = 'Check Pixelforge now for every generation still waiting on it';

    public function handle(): int
    {
        $generations = Generation::query()->awaitingProvider()->get();

        $generations->each(fn (Generation $generation) => CheckOnGeneration::dispatch($generation));

        $this->components->info("Queued a status check for {$generations->count()} generation(s).");

        return self::SUCCESS;
    }
}
