<?php

namespace App\Http\Middleware;

use App\Pixelforge\WebhookSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anyone can POST to the webhook URL, so only requests signed with our secret, recently, get through.
 */
final readonly class VerifyPixelforgeSignature
{
    public function __construct(
        private WebhookSignature $signature,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Pixelforge-Signature');

        if (! $this->signature->isValid($header, $request->getContent(), now()->getTimestamp())) {
            Log::warning('Rejected a Pixelforge webhook with an invalid or expired signature.', [
                'event_id' => $request->header('Pixelforge-Event-Id'),
            ]);

            abort(Response::HTTP_BAD_REQUEST, 'Invalid Pixelforge signature.');
        }

        return $next($request);
    }
}
