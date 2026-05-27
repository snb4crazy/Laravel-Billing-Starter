<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;

class PaymentDeniedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $resource = (array) data_get($payload, 'resource', []);

        $providerPaymentId = (string) data_get($resource, 'id', '');

        if ($providerPaymentId === '') {
            return;
        }

        $user = $this->resolveUser($resource);

        if (! $user) {
            return;
        }

        $amount = $this->resolveAmount($resource);
        $currency = (string) data_get($resource, 'amount.currency_code', 'USD');
        $reason = (string) data_get($resource, 'status_details.reason', 'CAPTURE_DENIED');

        Payment::query()->updateOrCreate([
            'provider' => $event->provider,
            'provider_payment_id' => $providerPaymentId,
        ], [
            'user_id' => $user->id,
            'status' => 'failed',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'metadata' => [
                'event_id' => $event->external_event_id,
                'failure_reason' => $reason,
                'status_details' => (array) data_get($resource, 'status_details', []),
            ],
        ]);
    }

    private function resolveUser(array $resource): ?User
    {
        // Look for user_id in supplementary_data or custom_id (if available)
        $userId = data_get($resource, 'supplementary_data.related_ids.order_id')
            ?? data_get($resource, 'custom_id');

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    private function resolveAmount(array $resource): int
    {
        $amount = (string) data_get($resource, 'amount.value', 0);
        
        // PayPal amount comes as decimal string (e.g., "19.99")
        // Convert to cents as integer
        return (int) round((float) $amount * 100);
    }
}

