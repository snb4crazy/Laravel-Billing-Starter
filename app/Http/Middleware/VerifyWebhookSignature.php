<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $provider = (string) $request->route('provider');
        $secret = (string) config("billing.webhooks.providers.{$provider}.signing_secret");

        if ($secret === '') {
            Log::warning('Billing webhook secret is not configured.', ['provider' => $provider]);

            return new JsonResponse([
                'message' => 'Webhook secret is not configured.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $timestamp = (int) $request->header('X-Billing-Timestamp', 0);
        $signature = (string) $request->header('X-Billing-Signature', '');
        $toleranceSeconds = max((int) config('billing.webhooks.tolerance_seconds', 300), 60);

        if ($timestamp <= 0 || $signature === '') {
            return new JsonResponse([
                'message' => 'Missing webhook signature headers.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (abs(now()->timestamp - $timestamp) > $toleranceSeconds) {
            return new JsonResponse([
                'message' => 'Webhook timestamp is outside the allowed tolerance.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $signedPayload = $timestamp.'.'.$request->getContent();
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return new JsonResponse([
                'message' => 'Invalid webhook signature.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

