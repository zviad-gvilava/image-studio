<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\CheckOnGeneration;
use App\Models\CreditTransaction;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class CheckOnGenerationTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    private const string PROVIDER_ID = 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K';

    public function test_it_settles_a_generation_whose_webhook_never_arrived(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        Http::fake(['*/v1/generations/'.self::PROVIDER_ID => Http::response($this->succeededObject(self::PROVIDER_ID))]);

        $this->runCheck($generation)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.381887500', $generation->price?->toString());
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_it_checks_again_later_while_the_generation_is_still_running(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Queued)->create();
        Http::fake(['*/v1/generations/*' => Http::response($this->generationObject(self::PROVIDER_ID, 'processing'))]);

        $this->runCheck($generation, attempt: 2)->assertReleased(CheckOnGeneration::DELAYS_SECONDS[2]);

        $this->assertSame(GenerationStatus::Processing, $generation->fresh()?->status);
    }

    public function test_it_does_nothing_once_a_webhook_has_settled_the_generation(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Succeeded)->create();
        Http::fake();

        $this->runCheck($generation)->assertNotReleased();

        Http::assertNothingSent();
    }

    public function test_it_stops_after_the_last_scheduled_check(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        Http::fake(['*/v1/generations/*' => Http::response($this->generationObject(self::PROVIDER_ID, 'processing'))]);

        $this->runCheck($generation, attempt: count(CheckOnGeneration::DELAYS_SECONDS))->assertNotReleased();
    }

    public function test_it_checks_again_when_pixelforge_is_busy(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        Http::fake(['*/v1/generations/*' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '30'])]);

        $this->runCheck($generation)->assertReleased(CheckOnGeneration::DELAYS_SECONDS[1]);
    }

    public function test_it_stops_when_pixelforge_does_not_know_the_generation(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        Http::fake(['*/v1/generations/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->runCheck($generation)->assertNotReleased();

        $this->assertSame(GenerationStatus::Processing, $generation->fresh()?->status);
    }

    private function runCheck(Generation $generation, int $attempt = 1): CheckOnGeneration
    {
        $job = (new CheckOnGeneration($generation->fresh() ?? $generation))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        app()->call([$job, 'handle']);

        return $job;
    }
}
