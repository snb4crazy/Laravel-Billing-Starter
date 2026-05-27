<?php

namespace App\Billing\Webhooks\Verifiers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic HMAC-SHA256 verifier using X-Billing-Timestamp / X-Billing-Signature headers.
 *
 * Use for custom or non-Stripe providers.
 * Signed payload format: {timestamp}.{raw_body}
 */
class HmacWebhookVerifier implements WebhookVerifier
{
    public function __construct(
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    public function verify(Request $request, string $secret): void
    {
        $timestamp = (int) $request->header('X-Billing-Timestamp', 0);
        $signature = (string) $request->header('X-Billing-Signature', '');

        if ($timestamp <= 0 || $signature === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing webhook signature headers.');
        }

        if (abs(now()->timestamp - $timestamp) > $this->toleranceSeconds) {
            abort(Response::HTTP_UNAUTHORIZED, 'Webhook timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid webhook signature.');
        }
    }
}

