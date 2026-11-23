<?php

namespace App\Http\Controllers;

use App\Billing\InsufficientCreditsException;
use App\Generations\StartGeneration;
use App\Http\Requests\StoreGenerationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class GenerationController extends Controller
{
    public function store(StoreGenerationRequest $request, StartGeneration $startGeneration, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $startGeneration($user, $request->options());
        } catch (InsufficientCreditsException $exception) {
            throw ValidationException::withMessages(['credits' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generation started. It will appear in your history.']);

        return to_route('studio');
    }
}
