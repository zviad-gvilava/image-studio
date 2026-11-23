<?php

namespace Database\Seeders;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A local admin with some credits to try things with. Password: "password".
     */
    public function run(CreditLedger $ledger): void
    {
        $user = User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $ledger->purchase($user, Money::of(10));
    }
}
