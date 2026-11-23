<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\CreditTransaction;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    private const string PROVIDER_ID = 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K';

    public function test_it_submits_with_our_id_as_idempotency_key_and_metadata(): void
    {
        $generation = Generation::factory()->create(['reference_images' => ['https://example.com/style.png']]);
        Http::fake(['*/v1/generations' => Http::response($this->generationObject(self::PROVIDER_ID, 'queued', $generation->id), 202)]);

        $this->runJob($generation);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $generation->id)
            && $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && $request['metadata'] === ['generation_id' => $generation->id]
            && $request['reference_images'] === ['https://example.com/style.png']
            && $request['prompt'] === $generation->prompt);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame(self::PROVIDER_ID, $generation->provider_id);
        $this->assertSame('12.50', $generation->rates?->toArray()['reasoning_tokens']);
    }

    public function test_an_idempotent_replay_that_already_succeeded_is_charged(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response($this->succeededObject(self::PROVIDER_ID, $generation->id), 202, ['Idempotent-Replayed' => 'true'])]);

        $this->runJob($generation);

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()?->status);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_it_waits_out_an_overloaded_provider(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response(['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']], 503, ['Retry-After' => '2'])]);

        $job = $this->runJob($generation);

        $job->assertReleased();
        $this->assertGreaterThanOrEqual(2, $job->job->releaseDelay);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame('overloaded', $generation->error_code);
    }

    public function test_it_waits_for_a_concurrency_slot_as_long_as_pixelforge_asks(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response(['error' => ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Busy.']], 429, ['Retry-After' => '5'])]);

        $job = $this->runJob($generation);

        $job->assertReleased();
        $this->assertGreaterThanOrEqual(5, $job->job->releaseDelay);
        $this->assertSame('concurrency_limit_exceeded', $generation->fresh()?->error_code);
    }

    public function test_it_retries_when_pixelforge_cannot_be_reached(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => fn () => throw new ConnectionException('Connection timed out')]);

        $this->runJob($generation)->assertReleased();

        $this->assertSame('connection_failed', $generation->fresh()?->error_code);
    }

    public function test_it_gives_up_on_an_overloaded_provider_after_a_few_attempts_and_charges_nothing(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response(['error' => ['code' => 'overloaded']], 503, ['Retry-After' => '2'])]);

        $this->runJob($generation, attempt: 5)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('overloaded', $generation->error_code);
        $this->assertTrue($generation->price?->isZero());
    }

    public function test_it_keeps_waiting_longer_for_a_free_concurrency_slot(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response(['error' => ['code' => 'concurrency_limit_exceeded']], 429, ['Retry-After' => '5'])]);

        $this->runJob($generation, attempt: 8)->assertReleased();
        $this->runJob($generation, attempt: 12)->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()?->status);
    }

    public function test_a_blocked_prompt_fails_at_once_and_is_free(): void
    {
        $generation = Generation::factory()->create(['prompt' => 'Something [blocked]']);
        Http::fake(['*/v1/generations' => Http::response(['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Rejected.']], 400)]);

        $this->runJob($generation)->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertTrue($generation->price?->isZero());
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_it_does_not_resubmit_a_generation_that_already_moved_on(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        Http::fake();

        $this->runJob($generation);

        Http::assertNothingSent();
    }

    public function test_a_generation_given_up_on_is_still_settled_if_pixelforge_accepted_it_after_all(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(['*/v1/generations' => Http::response(['error' => ['code' => 'overloaded']], 503)]);
        $this->runJob($generation, attempt: 5);

        $this->deliverWebhook($this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID, $generation->id)))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.381887500', $generation->price?->toString());
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    private function runJob(Generation $generation, int $attempt = 1): SubmitGeneration
    {
        $job = (new SubmitGeneration($generation->fresh() ?? $generation))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        app()->call([$job, 'handle']);

        return $job;
    }
}
