<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;

class PaymentCompletedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $details = $this->extractPaymentDetails($payload);

        $providerPaymentId = (string) data_get($details, 'id', '');

        if ($providerPaymentId === '') {
            return;
        }

        $user = $this->resolveUser($details);

        if (! $user) {
            return;
        }

        // Resolve the amount in cents (PayPal returns as string with decimals)
        $amount = $this->resolveAmount($details);
        $currency = (string) data_get($details, 'amount.currency_code', data_get($details, 'currency', 'USD'));

        Payment::query()->updateOrCreate([
            'provider' => $event->provider,
            'provider_payment_id' => $providerPaymentId,
        ], [
            'user_id' => $user->id,
            'status' => 'succeeded',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'paid_at' => now(),
            'metadata' => [
                'event_id' => $event->external_event_id,
                'supplementary_data' => (array) data_get($details, 'supplementary_data', []),
            ],
        ]);
    }

    private function extractPaymentDetails(array $payload): array
    {
        return (array) (data_get($payload, 'resource') ?? data_get($payload, 'data.object', []));
    }

    private function resolveUser(array $details): ?User
    {
        $userId = data_get($details, 'metadata.user_id')
            ?? data_get($details, 'user_id')
            ?? data_get($details, 'supplementary_data.related_ids.order_id')
            ?? data_get($details, 'custom_id');

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    private function resolveAmount(array $details): int
    {
        $paypalAmount = data_get($details, 'amount.value');

        if ($paypalAmount !== null) {
            return (int) round(((float) $paypalAmount) * 100);
        }

        return (int) data_get($details, 'amount', data_get($details, 'amount_total', 0));
    }
}

