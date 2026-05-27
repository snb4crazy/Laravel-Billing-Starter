<?php

namespace App\Billing\Webhooks\Verifiers;

use Illuminate\Http\Request;

/**
 * Contract for provider-specific webhook signature verification.
 *
 * Each payment provider uses a different signature scheme.
 * Implementing this interface isolates that difference from the middleware.
 *
 * Extraction note:
 *   Copy this interface + whichever concrete verifier you need into any app.
 */
interface WebhookVerifier
{
    /**
     * Verify the signature on the incoming webhook request.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException on failure
     */
    public function verify(Request $request, string $secret): void;
}

