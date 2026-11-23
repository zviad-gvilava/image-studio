<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfitReportRequest extends FormRequest
{
    /**
     * Pixelforge's usage report covers at most this many days per request.
     */
    public const int MAX_DAYS = 92;

    private const int DEFAULT_DAYS = 30;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->from()->isAfter($this->to())) {
                    $validator->errors()->add('from', 'The start date must be on or before the end date.');
                } elseif ($this->from()->diffInDays($this->to()) >= self::MAX_DAYS) {
                    $validator->errors()->add('from', 'Pick a range of '.self::MAX_DAYS.' days or fewer.');
                }
            },
        ];
    }

    /**
     * Report days are UTC days, because Pixelforge bills by the UTC day a generation completed.
     */
    public function from(): CarbonImmutable
    {
        return $this->day('from') ?? $this->to()->subDays(self::DEFAULT_DAYS - 1);
    }

    public function to(): CarbonImmutable
    {
        return $this->day('to') ?? CarbonImmutable::now('UTC')->startOfDay();
    }

    private function day(string $key): ?CarbonImmutable
    {
        return $this->filled($key)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string($key)->toString(), 'UTC') ?: null
            : null;
    }
}
