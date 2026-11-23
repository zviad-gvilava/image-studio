<?php

namespace App\Generations;

use App\Billing\CreditLedger;
use App\Billing\Markup;
use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\ErrorCode;
use App\Pixelforge\GenerationSnapshot;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

/**
 * The one place a generation's status and cost change. The create response, webhooks and
 * polling all go through here, so they all follow the same rules.
 */
final readonly class GenerationLifecycle
{
    public function __construct(
        private Markup $markup,
        private CreditLedger $ledger,
    ) {}

    /**
     * Brings our record in line with Pixelforge's view of the generation. Safe to call with
     * repeated, stale or out-of-order snapshots: status only moves forward, and a success is
     * priced from its own usage and rates and charged exactly once.
     */
    public function sync(Generation $generation, GenerationSnapshot $snapshot): void
    {
        DB::transaction(function () use ($generation, $snapshot): void {
            $generation = $this->lock($generation);

            if (! $generation->acceptsProviderStatus($snapshot->status)) {
                return;
            }

            $this->applySnapshot($generation, $snapshot);
            $generation->save();

            if ($snapshot->status === GenerationStatus::Succeeded) {
                $this->ledger->charge($generation);
            }
        });
    }

    /**
     * Ends a generation Pixelforge never accepted. Nothing was billed, so it is free.
     */
    public function reject(Generation $generation, ErrorCode $reason): void
    {
        DB::transaction(function () use ($generation, $reason): void {
            $generation = $this->lock($generation);

            if ($generation->status !== GenerationStatus::Pending) {
                return;
            }

            $generation->forceFill([
                'status' => GenerationStatus::Failed,
                'error_code' => $reason->value,
                'provider_cost' => Money::zero(),
                'price' => Money::zero(),
                'completed_at' => now(),
            ])->save();
        });
    }

    /**
     * Records why submission is being retried, so the user can see what they are waiting for.
     */
    public function noteRetry(Generation $generation, ErrorCode $reason, int $attempts): void
    {
        Generation::query()
            ->whereKey($generation->getKey())
            ->where('status', GenerationStatus::Pending)
            ->update(['error_code' => $reason->value, 'submission_attempts' => $attempts]);
    }

    private function applySnapshot(Generation $generation, GenerationSnapshot $snapshot): void
    {
        $usage = $snapshot->usage;
        $providerCost = $this->providerCost($snapshot);

        $generation->forceFill([
            'provider_id' => $snapshot->id,
            'status' => $snapshot->status,
            'rates' => $snapshot->rates,
            'input_text_tokens' => $usage?->inputTextTokens,
            'input_image_tokens' => $usage?->inputImageTokens,
            'output_tokens' => $usage?->outputTokens,
            'reasoning_tokens' => $usage?->reasoningTokens,
            'provider_cost' => $providerCost,
            'price' => $providerCost === null ? null : $this->markup->apply($providerCost),
            'image_url' => $snapshot->imageUrl,
            'image_url_expires_at' => $snapshot->imageUrlExpiresAt,
            'error_code' => $snapshot->errorCode,
            'started_at' => $snapshot->startedAt,
            'completed_at' => $snapshot->completedAt,
        ]);
    }

    /**
     * Pixelforge only bills succeeded generations; failed ones are recorded as free.
     */
    private function providerCost(GenerationSnapshot $snapshot): ?Money
    {
        return match ($snapshot->status) {
            GenerationStatus::Succeeded => $snapshot->rates->costOf(
                $snapshot->usage ?? throw new UnexpectedValueException('A succeeded generation must report its token usage.'),
            ),
            GenerationStatus::Failed => Money::zero(),
            default => null,
        };
    }

    private function lock(Generation $generation): Generation
    {
        return Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();
    }
}
