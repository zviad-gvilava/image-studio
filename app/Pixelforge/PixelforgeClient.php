<?php

namespace App\Pixelforge;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Talks to the Pixelforge API. Every failure surfaces as a PixelforgeException; retrying is up to the caller.
 */
final readonly class PixelforgeClient
{
    private const string PRICING_CACHE_KEY = 'pixelforge.pricing';

    public function __construct(
        #[Config('services.pixelforge.base_url')]
        private string $baseUrl,
        #[Config('services.pixelforge.api_key')]
        private ?string $apiKey,
        #[Config('services.pixelforge.timeout_seconds')]
        private int $timeoutSeconds,
        #[Config('services.pixelforge.pricing_cache_seconds')]
        private int $pricingCacheSeconds,
    ) {}

    /**
     * Retrying with the same idempotency key never creates a second generation.
     */
    public function createGeneration(GenerationParameters $parameters, string $idempotencyKey): GenerationSnapshot
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('generations', $parameters->toArray()));

        return GenerationSnapshot::fromArray($this->json($response));
    }

    public function generation(string $id): GenerationSnapshot
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('generations/'.rawurlencode($id)));

        return GenerationSnapshot::fromArray($this->json($response));
    }

    /**
     * Cached briefly: every price estimate needs it and it rarely changes.
     */
    public function pricing(): Pricing
    {
        /** @var array<array-key, mixed> $pricing */
        $pricing = Cache::remember(self::PRICING_CACHE_KEY, $this->pricingCacheSeconds, fn (): array => $this->json(
            $this->send(fn (PendingRequest $http): Response => $http->get('pricing')),
        ));

        return Pricing::fromArray($pricing);
    }

    /**
     * @return list<UsageDay>
     */
    public function usage(CarbonInterface $from, CarbonInterface $to): array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('usage', [
            'start_date' => $from->toDateString(),
            'end_date' => $to->toDateString(),
        ]));

        $days = $this->json($response)['data'] ?? null;

        if (! is_array($days)) {
            throw new UnexpectedValueException('Pixelforge sent a malformed usage report.');
        }

        return array_values(array_map(
            fn (mixed $day): UsageDay => UsageDay::fromArray(is_array($day) ? $day : []),
            $days,
        ));
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     */
    private function send(Closure $request): Response
    {
        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw PixelforgeException::connectionFailed($exception);
        }

        if ($response->failed()) {
            throw PixelforgeException::fromResponse($response);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($this->timeoutSeconds);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new UnexpectedValueException('Pixelforge sent a response that is not a JSON object.');
        }

        return $body;
    }
}
