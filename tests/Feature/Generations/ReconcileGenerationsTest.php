<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\CheckOnGeneration;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcileGenerationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_a_check_for_every_generation_still_waiting_on_pixelforge(): void
    {
        Queue::fake();
        $queued = Generation::factory()->accepted('gen_queued', GenerationStatus::Queued)->create();
        $processing = Generation::factory()->accepted('gen_processing', GenerationStatus::Processing)->create();
        Generation::factory()->accepted('gen_done', GenerationStatus::Succeeded)->create();
        Generation::factory()->create(['status' => GenerationStatus::Pending]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        Queue::assertPushed(CheckOnGeneration::class, 2);
        Queue::assertPushed(CheckOnGeneration::class, fn (CheckOnGeneration $job): bool => $job->generation->is($queued));
        Queue::assertPushed(CheckOnGeneration::class, fn (CheckOnGeneration $job): bool => $job->generation->is($processing));
    }
}
