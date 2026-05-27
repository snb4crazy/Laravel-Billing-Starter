<?php

namespace App\Http\Middleware;

use App\Billing\Webhooks\WebhookVerifierRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dispatches signature verification to the correct provider-specific verifier.
 *
 * - Stripe → StripeWebhookVerifier (Stripe-Signature header, Stripe SDK)
 * - Others → HmacWebhookVerifier (X-Billing-Timestamp / X-Billing-Signature headers)
 *
 * Adding support for a new provider only requires adding a verifier to WebhookVerifierRegistry.
 */
class VerifyWebhookSignature
{
    public function __construct(private readonly WebhookVerifierRegistry $registry)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $provider = (string) $request->route('provider');
        $secret = (string) config("billing.webhooks.providers.{$provider}.signing_secret");

        if ($secret === '') {
            return new JsonResponse([
                'message' => 'Webhook signing secret is not configured for this provider.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->registry->for($provider)->verify($request, $secret);

        return $next($request);
    }
}



