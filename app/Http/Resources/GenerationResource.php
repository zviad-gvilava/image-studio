<?php

namespace App\Http\Resources;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;

/**
 * @mixin Generation
 */
final class GenerationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'is_final' => $this->status->isFinal(),
            'prompt' => $this->prompt,
            'size' => $this->size->value,
            'size_label' => $this->size->label(),
            'quality' => $this->quality->value,
            'quality_label' => $this->quality->label(),
            'reference_images' => $this->reference_images,
            'estimated_min_price' => $this->estimated_min_price->display(),
            'estimated_max_price' => $this->estimated_max_price->display(),
            'price' => $this->price?->display(),
            'tokens' => $this->output_tokens === null ? null : [
                'input_text' => $this->input_text_tokens,
                'input_image' => $this->input_image_tokens,
                'output' => $this->output_tokens,
                'reasoning' => $this->reasoning_tokens,
            ],
            'image_url' => $this->imageIsAvailable() ? $this->image_url : null,
            'image_expired' => $this->image_url !== null && ! $this->imageIsAvailable(),
            'image_url_expires_at' => $this->image_url_expires_at?->toIso8601String(),
            'message' => $this->statusMessage(),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Why it failed, or what it is waiting for, in words a user understands.
     */
    private function statusMessage(): ?string
    {
        if ($this->error_code === null) {
            return null;
        }

        $group = $this->status === GenerationStatus::Failed ? 'failed' : 'retrying';
        $key = "generations.{$group}.{$this->error_code}";

        return (string) __(Lang::has($key) ? $key : "generations.{$group}.unknown");
    }
}
