<?php

namespace App\Pixelforge;

enum ErrorCode: string
{
    case ContentPolicyViolation = 'content_policy_violation';
    case ValidationFailed = 'validation_failed';
    case InvalidApiKey = 'invalid_api_key';
    case NotFound = 'not_found';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case ConcurrencyLimitExceeded = 'concurrency_limit_exceeded';
    case DailyLimitExceeded = 'daily_limit_exceeded';
    case Overloaded = 'overloaded';
    case GenerationFailed = 'generation_failed';
    case InternalError = 'internal_error';

    /** Not a Pixelforge code: the request never got an answer. */
    case ConnectionFailed = 'connection_failed';

    case Unknown = 'unknown';

    /**
     * Temporary conditions where trying again later is expected to work.
     */
    public function isTemporary(): bool
    {
        return match ($this) {
            self::RateLimitExceeded,
            self::ConcurrencyLimitExceeded,
            self::Overloaded,
            self::InternalError,
            self::ConnectionFailed => true,
            default => false,
        };
    }
}
