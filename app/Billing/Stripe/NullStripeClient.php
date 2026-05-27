<?php

namespace App\Billing\Stripe;

use App\Billing\Contracts\StripeClientInterface;
use Illuminate\Support\Str;

/**
 * No-op Stripe client used when STRIPE_SECRET_KEY is not set.
 *
 * Returns stub data so billing flows remain exercisable in test/CI
 * environments without a real Stripe account.
 *
 * Never used in production (AppServiceProvider binds StripeHttpClient
 * only when a real API key is present).
 */
class NullStripeClient implements StripeClientInterface
{
    public function findOrCreateCustomer(string $email, array $metadata = []): array
    {
        return ['id' => 'cus_null_'.Str::ulid()];
    }

    public function createCheckoutSession(array $params): array
    {
        $successUrl = (string) ($params['success_url'] ?? '');

        return [
            'id' => 'cs_null_'.Str::ulid(),
            'url' => rtrim($successUrl, '/').'?stub_checkout=1',
        ];
    }

    public function createSubscription(array $params): array
    {
        return [
            'id' => 'sub_null_'.Str::ulid(),
            'status' => 'incomplete',
        ];
    }

    public function constructWebhookEvent(string $rawPayload, string $signatureHeader, string $secret): array
    {
        return json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
    }
}

