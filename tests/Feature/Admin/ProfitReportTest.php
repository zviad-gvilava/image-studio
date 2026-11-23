<?php

namespace Tests\Feature\Admin;

use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_see_the_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.report'))
            ->assertForbidden();
    }

    public function test_it_groups_by_the_utc_day_pixelforge_completed_the_generation_and_checks_its_usage_report(): void
    {
        $this->travelTo('2026-11-23 12:00:00');
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);

        // Created on the 21st but completed just after midnight: Pixelforge bills it on the 22nd.
        $this->succeeded($alice, completedAt: '2026-11-22 00:00:05', cost: '0.305510000', createdAt: '2026-11-21 23:59:50');
        $this->succeeded($bob, completedAt: '2026-11-22 18:00:00', cost: '0.169080000');
        $this->succeeded($alice, completedAt: '2026-11-23 09:00:00', cost: '0.014000000');
        Generation::factory()->for($bob)->create([
            'status' => GenerationStatus::Failed,
            'provider_cost' => Money::zero(),
            'price' => Money::zero(),
            'completed_at' => '2026-11-23 10:00:00',
        ]);

        Http::fake(['*/v1/usage*' => Http::response(['object' => 'usage', 'data' => [
            ['date' => '2026-11-21', 'generations' => 0, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.000000000'],
            ['date' => '2026-11-22', 'generations' => 2, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.474590000'],
            ['date' => '2026-11-23', 'generations' => 1, 'input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'cost_usd' => '0.014000001'],
        ]])]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.report', ['from' => '2026-11-21', 'to' => '2026-11-23']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Report')
                ->where('totals.generations', 3)
                ->where('totals.provider_cost.exact', '0.488590000')
                ->where('totals.charged.exact', '0.610737500')
                ->where('totals.profit.exact', '0.122147500')
                ->where('totals.margin_percent', '20.0')
                ->where('totals.matches', false)
                ->has('days', 2)
                ->where('days.0.date', '2026-11-23')
                ->where('days.0.matches', false)
                ->where('days.1.date', '2026-11-22')
                ->where('days.1.generations', 2)
                ->where('days.1.provider_cost.exact', '0.474590000')
                ->where('days.1.matches', true)
                ->has('days.1.users', 2)
                ->has('users', 2)
                ->where('users.0.user.name', 'Alice')
                ->where('users.0.charged.exact', '0.399387500'));
    }

    public function test_it_still_shows_our_numbers_when_pixelforge_is_unavailable(): void
    {
        Http::fake(['*/v1/usage*' => Http::response(['error' => ['code' => 'overloaded']], 503)]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.report'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pixelforge_available', false)
                ->where('totals.matches', null));
    }

    public function test_it_rejects_ranges_longer_than_pixelforge_reports(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.report', ['from' => '2026-01-01', 'to' => '2026-06-01']))
            ->assertSessionHasErrors('from');
    }

    private function succeeded(User $user, string $completedAt, string $cost, ?string $createdAt = null): void
    {
        $providerCost = Money::of($cost);

        Generation::factory()->for($user)->create([
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => $providerCost,
            'price' => Money::ofNanos(intdiv($providerCost->nanos * 125, 100)),
            'completed_at' => $completedAt,
            'created_at' => $createdAt ?? $completedAt,
        ]);
    }
}
