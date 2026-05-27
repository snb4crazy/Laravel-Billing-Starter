<?php

namespace App\Billing\Webhooks\Verifiers;

use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe-native webhook verifier.x
 *
 * Uses the official Stripe SDK Webhook::constructEvent() which:
 *   - Reads the Stripe-Signature header (automatic replay protection).
 *   - Verifies HMAC-SHA256 with a configurable tolerance window.
 *   - Returns the verified event data.
 *
 * Stripe signature header format:
 *   Stripe-Signature: t={unix_timestamp},v1={signature}
 *
 * Extraction note:
 *   Requires `stripe/stripe-php`. Copy this class + WebhookVerifier interface
 *   into any app to get Stripe signature verification.
 */
class StripeWebhookVerifier implements WebhookVerifier
{
    public function __construct(
        private readonly int $toleranceSeconds = 300,
    ) {
    }

    public function verify(Request $request, string $secret): void
    {
        $sigHeader = (string) $request->header('Stripe-Signature', '');

        if ($sigHeader === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing Stripe-Signature header.');
        }

        try {
            Webhook::constructEvent(
                $request->getContent(),
                $sigHeader,
                $secret,
                $this->toleranceSeconds,
            );
        } catch (\UnexpectedValueException $exception) {
            abort(Response::HTTP_BAD_REQUEST, 'Invalid Stripe webhook payload: '.$exception->getMessage());
        } catch (SignatureVerificationException $exception) {
            abort(Response::HTTP_UNAUTHORIZED, 'Stripe webhook signature verification failed: '.$exception->getMessage());
        }
    }
}

