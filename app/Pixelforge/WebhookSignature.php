<?php

namespace App\Pixelforge;

use Illuminate\Container\Attributes\Config;

/**
 * Checks the Pixelforge-Signature header: "t=<unix timestamp>,v1=<hex HMAC-SHA256 of '<timestamp>.<raw body>'>".
 */
final readonly class WebhookSignature
{
    public function __construct(
        #[Config('services.pixelforge.webhook_secret')]
        private ?string $secret,
        #[Config('services.pixelforge.webhook_tolerance_seconds')]
        private int $toleranceSeconds,
    ) {}

    public function isValid(string $header, string $payload, int $now): bool
    {
        if ($this->secret === null || $this->secret === '') {
            return false;
        }

        $parts = $this->parse($header);
        $timestamp = filter_var($parts['t'] ?? null, FILTER_VALIDATE_INT);

        if (! is_int($timestamp) || abs($now - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);

        return hash_equals($expected, $parts['v1'] ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $header): array
    {
        $parts = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }
}
