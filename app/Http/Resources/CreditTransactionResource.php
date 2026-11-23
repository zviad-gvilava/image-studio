<?php

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin CreditTransaction
 */
final class CreditTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'amount' => $this->amount->display(),
            'description' => $this->generation === null ? null : Str::limit($this->generation->prompt, 80),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
