<?php

namespace Tests\Feature\Generations;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithPixelforge;
use Tests\TestCase;

class StartGenerationTest extends TestCase
{
    use InteractsWithPixelforge;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakePricing();
    }

    public function test_guests_cannot_generate(): void
    {
        $this->post(route('generations.store'), $this->validInput())->assertRedirect(route('login'));
    }

    public function test_it_saves_the_generation_with_its_estimate_and_submits_it_in_the_background(): void
    {
        $user = $this->userWithCredits('5');

        $this->actingAs($user)
            ->post(route('generations.store'), $this->validInput())
            ->assertRedirect(route('studio'));

        $generation = Generation::query()->sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame($user->id, $generation->user_id);
        $this->assertSame(['https://example.com/a.png', 'https://example.com/b.png'], $generation->reference_images);
        // The documented worst case: 400 characters, 2 reference images, high quality, 1536x1024.
        $this->assertSame('0.467343750', $generation->estimated_max_price->toString());
        $this->assertSame('0.357781250', $generation->estimated_min_price->toString());

        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
    }

    public function test_it_refuses_a_generation_the_user_cannot_afford(): void
    {
        $user = $this->userWithCredits('0.40');

        $this->actingAs($user)
            ->post(route('generations.store'), $this->validInput())
            ->assertSessionHasErrors('credits');

        $this->assertSame(0, Generation::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_generations_in_progress_hold_their_worst_case_price(): void
    {
        $user = $this->userWithCredits('0.50');
        Generation::factory()->for($user)->create(['estimated_max_price' => Money::of('0.10')]);

        $this->actingAs($user)
            ->post(route('generations.store'), $this->validInput())
            ->assertSessionHasErrors('credits');

        $this->assertSame('0.400000000', app(CreditLedger::class)->summary($user)->available->toString());
    }

    public function test_it_validates_the_options(): void
    {
        $user = $this->userWithCredits('5');

        $this->actingAs($user)
            ->post(route('generations.store'), [
                'prompt' => '',
                'size' => '640x480',
                'quality' => 'ultra',
                'reference_images' => ['ftp://example.com/a.png', 'https://a.test/1.png', 'https://a.test/2.png', 'https://a.test/3.png', 'https://a.test/4.png'],
            ])
            ->assertSessionHasErrors(['prompt', 'size', 'quality', 'reference_images', 'reference_images.0']);

        $this->actingAs($user)
            ->post(route('generations.store'), [...$this->validInput(), 'prompt' => str_repeat('a', 4001)])
            ->assertSessionHasErrors('prompt');

        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(): array
    {
        return [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
        ];
    }

    private function userWithCredits(string $dollars): User
    {
        $user = User::factory()->create();
        app(CreditLedger::class)->purchase($user, Money::of($dollars));

        return $user;
    }
}
