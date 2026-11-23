<?php

namespace App\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use LogicException;

/**
 * Credits are US dollars. A balance is the sum of the user's append-only transactions,
 * so it can always be audited and never drifts from its history.
 */
final readonly class CreditLedger
{
    public function balance(User $user): Money
    {
        return Money::ofNanos((int) $user->creditTransactions()->sum('amount'));
    }

    /**
     * The most that generations still in progress can be charged.
     */
    public function reserved(User $user): Money
    {
        return Money::ofNanos((int) $user->generations()->inProgress()->sum('estimated_max_price'));
    }

    public function summary(User $user): CreditSummary
    {
        $balance = $this->balance($user);
        $reserved = $this->reserved($user);

        return new CreditSummary($balance, $reserved, $balance->minus($reserved));
    }

    /**
     * @throws InsufficientCreditsException
     */
    public function assertCanAfford(User $user, Money $price): void
    {
        $available = $this->summary($user)->available;

        if ($available->isLessThan($price)) {
            throw new InsufficientCreditsException($price, $available);
        }
    }

    public function purchase(User $user, Money $amount): CreditTransaction
    {
        return $user->creditTransactions()->create([
            'type' => CreditTransactionType::Purchase,
            'amount' => $amount,
        ]);
    }

    /**
     * The unique generation_id column makes a second charge for the same generation impossible.
     */
    public function charge(Generation $generation): CreditTransaction
    {
        $price = $generation->price ?? throw new LogicException('Only a priced generation can be charged.');

        return CreditTransaction::query()->create([
            'user_id' => $generation->user_id,
            'type' => CreditTransactionType::GenerationCharge,
            'amount' => $price->negated(),
            'generation_id' => $generation->id,
        ]);
    }
}
