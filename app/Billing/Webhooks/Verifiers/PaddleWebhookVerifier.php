<?php

namespace App\Billing\Webhooks\Verifiers;

use App\Billing\Contracts\PaddleClientInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paddle webhook verifier using Paddle's HMAC-SHA256 signature scheme.
 *
 * Paddle signs webhooks with: HMAC-SHA256("{event_id}:{occurred_at}:{webhook_payload}", secret)
 */
class PaddleWebhookVerifier implements WebhookVerifier
{
    public function __construct(private readonly PaddleClientInterface $paddle)
    {
    }

    public function verify(Request $request, string $secret): void
    {
        if ($secret === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'Paddle webhook secret is not configured.');
        }

        $eventId = (string) $request->input('event_id', '');
        $occurredAt = (string) $request->input('occurred_at', '');
        $signature = (string) $request->header('Paddle-Signature', '');

        if ($eventId === '' || $occurredAt === '' || $signature === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing Paddle webhook fields.');
        }

        // Paddle sends the raw JSON body, we need the exact raw payload for verification
        $webhookPayload = $request->getContent();

        $isValid = $this->paddle->verifyWebhookSignature([
            'event_id' => $eventId,
            'occurred_at' => $occurredAt,
            'webhook_payload' => $webhookPayload,
            'signature' => $signature,
        ]);

        if (! $isValid) {
            abort(Response::HTTP_UNAUTHORIZED, 'Paddle webhook signature verification failed.');
        }
    }
}

