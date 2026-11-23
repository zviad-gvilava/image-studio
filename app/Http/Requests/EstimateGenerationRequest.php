<?php

namespace App\Http\Requests;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use App\Generations\GenerationOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates generation options. A prompt is optional here, so the price can be shown while it is being typed.
 */
class EstimateGenerationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['nullable', 'string', 'max:'.GenerationOptions::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['array', 'max:'.GenerationOptions::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'You can add up to :max reference images.',
            'reference_images.*.url' => 'Reference images must be http or https links.',
            'reference_images.*.required' => 'Remove empty reference image links.',
        ];
    }

    public function options(): GenerationOptions
    {
        /** @var list<string> $referenceImages */
        $referenceImages = array_values($this->array('reference_images'));

        return new GenerationOptions(
            prompt: $this->string('prompt')->toString(),
            size: $this->enum('size', ImageSize::class) ?? ImageSize::Square,
            quality: $this->enum('quality', ImageQuality::class) ?? ImageQuality::Medium,
            referenceImages: $referenceImages,
        );
    }
}
