<?php

namespace App\Http\Controllers;

use App\Billing\CreditLedger;
use App\Billing\Money;
use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Models\User;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class CreditController extends Controller
{
    /**
     * @param  list<int>  $creditPacks
     */
    public function index(#[CurrentUser] User $user, #[Config('billing.credit_packs')] array $creditPacks): Response
    {
        return Inertia::render('credits/Index', [
            'packs' => array_map(fn (int $dollars): array => [
                'amount' => $dollars,
                'label' => Money::of($dollars)->format(),
            ], $creditPacks),
            'transactions' => CreditTransactionResource::collection(
                $user->creditTransactions()->with('generation')->latest()->latest('id')->paginate(20),
            ),
        ]);
    }

    /**
     * A simulated purchase: no payment is taken.
     */
    public function store(PurchaseCreditsRequest $request, CreditLedger $ledger, #[CurrentUser] User $user): RedirectResponse
    {
        $amount = $request->amount();

        $ledger->purchase($user, $amount);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added {$amount->format()} in credits."]);

        return back();
    }
}
