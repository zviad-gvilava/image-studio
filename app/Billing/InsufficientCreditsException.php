<?php

namespace App\Billing;

use RuntimeException;

final class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly Money $required,
        public readonly Money $available,
    ) {
        parent::__construct(sprintf(
            'This generation can cost up to %s, but you have %s available. Buy credits to continue.',
            $required->format(),
            $available->format(),
        ));
    }
}
