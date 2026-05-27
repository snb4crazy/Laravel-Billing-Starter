<?php

namespace App\Billing\PayPal;

use App\Billing\Contracts\PayPalClientInterface;
use Illuminate\Support\Str;

class NullPayPalClient implements PayPalClientInterface
{
    public function createCheckoutOrder(array $params): array
    {
        $returnUrl = (string) ($params['application_context']['return_url'] ?? config('app.url'));

        return [
            'id' => 'ORDER-NULL-'.Str::upper(Str::random(10)),
            'status' => 'CREATED',
            'approve_url' => rtrim($returnUrl, '/').'?stub_paypal_checkout=1',
        ];
    }

    public function createSubscription(array $params): array
    {
        $returnUrl = (string) ($params['application_context']['return_url'] ?? config('app.url'));

        return [
            'id' => 'SUBSCRIPTION-NULL-'.Str::upper(Str::random(10)),
            'status' => 'APPROVAL_PENDING',
            'approve_url' => rtrim($returnUrl, '/').'?stub_paypal_subscription=1',
        ];
    }

    public function verifyWebhookSignature(array $params): bool
    {
        return true;
    }
}

