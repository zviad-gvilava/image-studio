<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly ?int $status = null,
        public readonly ?int $retryAfterSeconds = null,
        string $message = '',
        ?ConnectionException $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $code = ErrorCode::tryFrom((string) $response->json('error.code')) ?? ErrorCode::Unknown;
        $retryAfter = $response->header('Retry-After');

        return new self(
            errorCode: $code,
            status: $response->status(),
            retryAfterSeconds: is_numeric($retryAfter) ? (int) $retryAfter : null,
            message: sprintf('Pixelforge responded %d (%s): %s', $response->status(), $code->value, (string) $response->json('error.message')),
        );
    }

    public static function connectionFailed(ConnectionException $exception): self
    {
        return new self(
            errorCode: ErrorCode::ConnectionFailed,
            message: 'Could not reach Pixelforge: '.$exception->getMessage(),
            previous: $exception,
        );
    }

    public function isTemporary(): bool
    {
        return $this->errorCode->isTemporary() || ($this->status ?? 0) >= 500;
    }
}
