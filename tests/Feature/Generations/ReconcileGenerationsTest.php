<?php

namespace Tests\Feature\Generations;

use App\Enums\GenerationStatus;
use App\Models\CreditTransaction;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class ReconcileGenerationsTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    public function test_it_polls_generations_whose_webhooks_are_overdue(): void
    {
        $overdue = Generation::factory()->accepted('gen_overdue', GenerationStatus::Processing)->create();
        $overdue->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();
        $recent = Generation::factory()->accepted('gen_recent', GenerationStatus::Processing)->create();

        Http::fake(['*/v1/generations/gen_overdue' => Http::response($this->succeededObject('gen_overdue'))]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        $this->assertSame(GenerationStatus::Succeeded, $overdue->fresh()?->status);
        $this->assertSame(GenerationStatus::Processing, $recent->fresh()?->status);
        $this->assertSame(1, CreditTransaction::query()->count());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v1/generations/gen_overdue'));
    }

    public function test_it_stops_early_when_pixelforge_asks_it_to_slow_down(): void
    {
        foreach (['gen_a', 'gen_b'] as $id) {
            Generation::factory()->accepted($id)->create()->forceFill(['updated_at' => now()->subMinutes(5)])->saveQuietly();
        }

        Http::fake(['*/v1/generations/*' => Http::response(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '30'])]);

        $this->artisan('generations:reconcile')->assertSuccessful();

        Http::assertSentCount(1);
    }
}
