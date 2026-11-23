<?php

namespace App\Generations;

use App\Billing\CreditLedger;
use App\Billing\InsufficientCreditsException;
use App\Billing\PriceEstimator;
use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class StartGeneration
{
    public function __construct(
        private PriceEstimator $estimator,
        private CreditLedger $ledger,
    ) {}

    /**
     * Saves the generation before Pixelforge hears of it, so a webhook can never arrive for
     * something we don't know about. Submission happens in the background.
     *
     * @throws InsufficientCreditsException
     */
    public function __invoke(User $user, GenerationOptions $options): Generation
    {
        $estimate = $this->estimator->estimate($options);

        $generation = DB::transaction(function () use ($user, $options, $estimate): Generation {
            // One spending decision per user at a time, so parallel requests can't overspend.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $this->ledger->assertCanAfford($user, $estimate->maxPrice);

            return $user->generations()->create([
                'status' => GenerationStatus::Pending,
                'prompt' => $options->prompt,
                'size' => $options->size,
                'quality' => $options->quality,
                'reference_images' => $options->referenceImages,
                'estimated_min_price' => $estimate->minPrice,
                'estimated_max_price' => $estimate->maxPrice,
            ]);
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
