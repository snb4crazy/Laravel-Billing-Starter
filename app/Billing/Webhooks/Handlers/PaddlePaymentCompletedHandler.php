<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;

class PaddlePaymentCompletedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $data = (array) data_get($payload, 'data', []);

        // Paddle transaction ID
        $providerPaymentId = (string) data_get($data, 'id', '');

        if ($providerPaymentId === '') {
            return;
        }

        $user = $this->resolveUser($data);

        if (! $user) {
            return;
        }

        $amount = (int) data_get($data, 'details.total', 0);
        $currency = (string) data_get($data, 'currency_code', 'USD');

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
                'subscription_id' => (string) data_get($data, 'subscription_id', ''),
                'receipt_number' => (string) data_get($data, 'receipt_number', ''),
            ],
        ]);
    }

    private function resolveUser(array $data): ?User
    {
        $userId = data_get($data, 'custom_data.user_id');

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }
}

