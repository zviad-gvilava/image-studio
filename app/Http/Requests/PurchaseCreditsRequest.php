<?php

namespace App\Http\Requests;

use App\Billing\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', Rule::in(config('billing.credit_packs'))],
        ];
    }

    public function amount(): Money
    {
        return Money::of($this->integer('amount'));
    }
}
