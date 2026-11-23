<?php

namespace App\Http\Resources;

use App\Billing\PriceEstimate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PriceEstimate $resource
 */
final class PriceEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $estimate = $this->resource;

        return [
            'min_price' => $estimate->minPrice->display(),
            'max_price' => $estimate->maxPrice->display(),
            'min_cost' => $estimate->minCost->display(),
            'max_cost' => $estimate->maxCost->display(),
            'markup_percent' => config('billing.markup_percent'),
            'tokens' => [
                'input_text' => ['min' => $estimate->minUsage->inputTextTokens, 'max' => $estimate->maxUsage->inputTextTokens],
                'input_image' => $estimate->maxUsage->inputImageTokens,
                'output' => $estimate->maxUsage->outputTokens,
                'reasoning' => ['min' => $estimate->minUsage->reasoningTokens, 'max' => $estimate->maxUsage->reasoningTokens],
            ],
        ];
    }
}
