<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case GenerationCharge = 'generation_charge';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Credit purchase',
            self::GenerationCharge => 'Image generation',
        };
    }
}
