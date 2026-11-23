<?php

namespace App\Billing;

final readonly class CreditSummary
{
    public function __construct(
        public Money $balance,
        public Money $reserved,
        public Money $available,
    ) {}

    /**
     * @return array{balance: array{exact: string, formatted: string}, reserved: array{exact: string, formatted: string}, available: array{exact: string, formatted: string}}
     */
    public function toArray(): array
    {
        return [
            'balance' => $this->balance->display(),
            'reserved' => $this->reserved->display(),
            'available' => $this->available->display(),
        ];
    }
}
