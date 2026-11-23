<?php

namespace App\Http\Requests;

use App\Generations\GenerationOptions;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreGenerationRequest extends EstimateGenerationRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'prompt' => ['required', 'string', 'max:'.GenerationOptions::MAX_PROMPT_LENGTH],
        ];
    }
}
