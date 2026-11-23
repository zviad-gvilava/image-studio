<?php

namespace App\Http\Controllers;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationOptions;
use App\Http\Resources\GenerationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

final class StudioController extends Controller
{
    private const int PER_PAGE = 12;

    public function __invoke(#[CurrentUser] User $user): Response
    {
        return Inertia::render('Studio', [
            'generations' => fn () => GenerationResource::collection(
                $user->generations()->latest()->latest('id')->paginate(self::PER_PAGE),
            ),
            'options' => [
                'sizes' => array_map(fn (ImageSize $size): array => ['value' => $size->value, 'label' => $size->label()], ImageSize::cases()),
                'qualities' => array_map(fn (ImageQuality $quality): array => ['value' => $quality->value, 'label' => $quality->label()], ImageQuality::cases()),
                'max_prompt_length' => GenerationOptions::MAX_PROMPT_LENGTH,
                'max_reference_images' => GenerationOptions::MAX_REFERENCE_IMAGES,
            ],
        ]);
    }
}
