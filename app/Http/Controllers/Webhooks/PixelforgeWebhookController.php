<?php

namespace App\Http\Controllers\Webhooks;

use App\Generations\HandlePixelforgeEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PixelforgeWebhookController
{
    public function __invoke(Request $request, HandlePixelforgeEvent $handle): Response
    {
        $event = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'data' => ['sometimes', 'array'],
        ]);

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $handle($event['id'], $event['type'], $payload);

        return response()->noContent();
    }
}
