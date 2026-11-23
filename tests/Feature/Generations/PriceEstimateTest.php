<?php

namespace Tests\Feature\Generations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class PriceEstimateTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    public function test_it_shows_the_price_range_and_its_breakdown(): void
    {
        $this->fakePricing();

        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), [
                'prompt' => str_repeat('a', 400),
                'size' => '1536x1024',
                'quality' => 'high',
                'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
            ])
            ->assertOk()
            ->assertJsonPath('data.max_cost.exact', '0.373875000')
            ->assertJsonPath('data.max_price.exact', '0.467343750')
            ->assertJsonPath('data.min_price.exact', '0.357781250')
            ->assertJsonPath('data.tokens.input_text', ['min' => 85, 'max' => 115])
            ->assertJsonPath('data.tokens.input_image', 1530)
            ->assertJsonPath('data.tokens.output', 6450)
            ->assertJsonPath('data.tokens.reasoning', ['min' => 1000, 'max' => 8000])
            ->assertJsonPath('data.markup_percent', 25);
    }

    public function test_it_prices_while_the_prompt_is_still_empty(): void
    {
        $this->fakePricing();

        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), ['prompt' => '', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.tokens.input_text', ['min' => 0, 'max' => 0])
            ->assertJsonPath('data.min_price.exact', '0.014000000');
    }

    public function test_it_counts_characters_not_bytes(): void
    {
        $this->fakePricing();

        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), ['prompt' => 'ééééé', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.tokens.input_text', ['min' => 1, 'max' => 3]);
    }

    public function test_it_explains_when_pricing_is_unavailable(): void
    {
        Http::fake(['*/v1/pricing' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('generations.estimate'), ['prompt' => 'A cat', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', __('generations.provider_unavailable'));
    }
}
