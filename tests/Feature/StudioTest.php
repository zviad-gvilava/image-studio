<?php

namespace Tests\Feature;

use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('studio'))->assertRedirect(route('login'));
    }

    public function test_users_see_only_their_own_generations_newest_first(): void
    {
        $user = User::factory()->create();
        Generation::factory()->for($user)->create(['prompt' => 'Older', 'created_at' => now()->subMinute()]);
        Generation::factory()->for($user)->create([
            'prompt' => 'Newer',
            'status' => GenerationStatus::Failed,
            'error_code' => 'content_policy_violation',
            'price' => Money::zero(),
        ]);
        Generation::factory()->create(['prompt' => 'Someone else']);

        $this->actingAs($user)
            ->get(route('studio'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Studio')
                ->has('generations.data', 2)
                ->where('generations.data.0.prompt', 'Newer')
                ->where('generations.data.0.message', __('generations.failed.content_policy_violation'))
                ->where('generations.data.0.price.exact', '0.000000000')
                ->where('generations.data.1.prompt', 'Older')
                ->has('options.sizes', 3)
                ->has('options.qualities', 3));
    }

    public function test_a_waiting_generation_explains_what_it_is_waiting_for(): void
    {
        $user = User::factory()->create();
        Generation::factory()->for($user)->create(['error_code' => 'concurrency_limit_exceeded']);

        $this->actingAs($user)
            ->get(route('studio'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('generations.data.0.status', 'pending')
                ->where('generations.data.0.message', __('generations.retrying.concurrency_limit_exceeded')));
    }
}
