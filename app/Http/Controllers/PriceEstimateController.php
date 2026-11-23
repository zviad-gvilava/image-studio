<?php

namespace App\Http\Controllers;

use App\Billing\PriceEstimator;
use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Resources\PriceEstimateResource;

final class PriceEstimateController extends Controller
{
    public function __invoke(EstimateGenerationRequest $request, PriceEstimator $estimator): PriceEstimateResource
    {
        return new PriceEstimateResource($estimator->estimate($request->options()));
    }
}
