<?php

namespace Tests\Feature\Webhooks;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    private const string PROVIDER_ID = 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K';

    public function test_it_rejects_requests_without_a_valid_signature(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID)->create();
        $event = $this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID));

        $this->deliverWebhook($event, secret: 'whsec_someone_else')->assertBadRequest();
        $this->postJson(route('webhooks.pixelforge'), $event)->assertBadRequest();

        $this->assertSame(GenerationStatus::Queued, $generation->fresh()?->status);
        $this->assertSame(0, WebhookEvent::query()->count());
    }

    public function test_it_rejects_replayed_requests_with_an_old_timestamp(): void
    {
        Generation::factory()->accepted(self::PROVIDER_ID)->create();
        $event = $this->event('evt_1', 'generation.processing', $this->generationObject(self::PROVIDER_ID, 'processing'));

        $this->deliverWebhook($event, timestamp: now()->subMinutes(6)->getTimestamp())->assertBadRequest();
    }

    public function test_a_succeeded_event_records_the_exact_cost_and_charges_the_user_once(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();

        $this->deliverWebhook($this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID)))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.305510000', $generation->provider_cost?->toString());
        $this->assertSame('0.381887500', $generation->price?->toString());
        $this->assertSame([12, 765, 6450, 3184], [$generation->input_text_tokens, $generation->input_image_tokens, $generation->output_tokens, $generation->reasoning_tokens]);
        $this->assertSame('2026-11-04 14:03:29', $generation->completed_at?->toDateTimeString());
        $this->assertNotNull($generation->image_url);

        $charge = CreditTransaction::query()->sole();
        $this->assertSame(CreditTransactionType::GenerationCharge, $charge->type);
        $this->assertSame('-0.381887500', $charge->amount->toString());
        $this->assertSame($generation->user_id, $charge->user_id);
    }

    public function test_a_redelivered_event_is_only_recorded_once(): void
    {
        Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        $event = $this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID));

        $this->deliverWebhook($event)->assertNoContent();
        $this->deliverWebhook($event)->assertNoContent();

        $this->assertSame(1, WebhookEvent::query()->count());
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_a_second_success_event_for_the_same_generation_is_not_charged_again(): void
    {
        Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();

        $this->deliverWebhook($this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID)));
        $this->deliverWebhook($this->event('evt_2', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID)));

        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_a_late_processing_event_does_not_move_a_finished_generation_back(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID)->create();

        $this->deliverWebhook($this->event('evt_2', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID)));
        $this->deliverWebhook($this->event('evt_1', 'generation.processing', $this->generationObject(self::PROVIDER_ID, 'processing')))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('0.381887500', $generation->price?->toString());
    }

    public function test_a_failed_generation_is_free(): void
    {
        $generation = Generation::factory()->accepted(self::PROVIDER_ID, GenerationStatus::Processing)->create();
        $failed = $this->generationObject(self::PROVIDER_ID, 'failed', overrides: [
            'error' => ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'],
        ]);

        $this->deliverWebhook($this->event('evt_1', 'generation.failed', $failed))->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('generation_failed', $generation->error_code);
        $this->assertTrue($generation->price?->isZero());
        $this->assertTrue($generation->provider_cost?->isZero());
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_an_event_that_arrives_before_the_create_response_is_matched_by_our_metadata(): void
    {
        $generation = Generation::factory()->create(['status' => GenerationStatus::Pending]);

        $this->deliverWebhook($this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID, $generation->id)))
            ->assertNoContent();

        $generation->refresh();
        $this->assertSame(self::PROVIDER_ID, $generation->provider_id);
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(1, CreditTransaction::query()->count());
    }

    public function test_metadata_cannot_redirect_an_event_to_a_generation_linked_to_another_provider_id(): void
    {
        $generation = Generation::factory()->accepted('gen_other')->create();

        $this->deliverWebhook($this->event('evt_1', 'generation.succeeded', $this->succeededObject(self::PROVIDER_ID, $generation->id)))
            ->assertNoContent();

        $this->assertSame(GenerationStatus::Queued, $generation->fresh()?->status);
        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_test_pings_are_acknowledged(): void
    {
        $this->deliverWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-04T14:03:29Z', 'data' => []])
            ->assertNoContent();
    }
}
