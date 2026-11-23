<?php

namespace Tests\Feature;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_buy_a_credit_pack(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('credits.index'))
            ->post(route('credits.store'), ['amount' => 10])
            ->assertRedirect(route('credits.index'));

        $purchase = CreditTransaction::query()->sole();
        $this->assertSame(CreditTransactionType::Purchase, $purchase->type);
        $this->assertSame('10.000000000', $purchase->amount->toString());
        $this->assertSame('10.000000000', app(CreditLedger::class)->summary($user)->balance->toString());
    }

    public function test_only_the_offered_packs_can_be_bought(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('credits.store'), ['amount' => 1_000_000])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, CreditTransaction::query()->count());
    }

    public function test_the_credits_page_shows_the_balance_and_history(): void
    {
        $user = User::factory()->create();
        app(CreditLedger::class)->purchase($user, Money::of(5));

        $this->actingAs($user)
            ->get(route('credits.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('credits/Index')
                ->where('credits.balance.formatted', '$5.00')
                ->where('credits.available.exact', '5.000000000')
                ->has('packs', 3)
                ->has('transactions.data', 1));
    }
}
