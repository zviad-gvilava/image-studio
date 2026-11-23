<?php

namespace Tests\Concerns;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Pixelforge payloads as documented, and a way to deliver signed webhooks.
 */
trait InteractsWithPixelforge
{
    protected const array RATES = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    protected function fakePricing(): void
    {
        Http::fake(['*/v1/pricing' => Http::response($this->pricing())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function pricing(): array
    {
        return [
            'object' => 'pricing',
            'currency' => 'usd',
            'unit' => 'per_1m_tokens',
            'rates' => self::RATES,
            'tokens' => [
                'characters_per_text_token' => 4,
                'text_token_variance' => 0.15,
                'per_reference_image' => 765,
                'output' => [
                    'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
                ],
                'reasoning' => [
                    'low' => ['min' => 0, 'max' => 500],
                    'medium' => ['min' => 200, 'max' => 2000],
                    'high' => ['min' => 1000, 'max' => 8000],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function generationObject(string $id, string $status, ?string $generationId = null, array $overrides = []): array
    {
        return [
            'id' => $id,
            'object' => 'generation',
            'status' => $status,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/style.png'],
            'metadata' => $generationId === null ? [] : ['generation_id' => $generationId],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => self::RATES,
            'error' => null,
            'created_at' => '2026-11-04T14:03:11Z',
            'started_at' => in_array($status, ['processing', 'succeeded', 'failed'], true) ? '2026-11-04T14:03:13Z' : null,
            'completed_at' => in_array($status, ['succeeded', 'failed'], true) ? '2026-11-04T14:03:29Z' : null,
            ...$overrides,
        ];
    }

    /**
     * The succeeded generation from the API docs: 12 text, 765 image, 6,450 output and 3,184 reasoning tokens.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function succeededObject(string $id, ?string $generationId = null, array $overrides = []): array
    {
        return $this->generationObject($id, 'succeeded', $generationId, [
            'image_url' => "https://pixelforge.promptedit.dev/images/{$id}.png?expires=1762351409&signature=abc",
            'image_url_expires_at' => now()->addDay()->toIso8601ZuluString(),
            'usage' => [
                'input_text_tokens' => 12,
                'input_image_tokens' => 765,
                'output_tokens' => 6450,
                'reasoning_tokens' => 3184,
                'total_tokens' => 10411,
            ],
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    protected function event(string $eventId, string $type, array $object): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'created_at' => '2026-11-04T14:03:29Z',
            'data' => ['object' => $object],
        ];
    }

    /**
     * Delivers an event the way Pixelforge does: an HMAC-SHA256 of "<timestamp>.<raw body>".
     *
     * @param  array<string, mixed>  $event
     * @return TestResponse<Response>
     */
    protected function deliverWebhook(array $event, ?int $timestamp = null, string $secret = 'whsec_test_secret'): TestResponse
    {
        $body = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->getTimestamp();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return $this->call('POST', route('webhooks.pixelforge'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_PIXELFORGE_EVENT_ID' => $event['id'],
            'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], content: $body);
    }
}
