<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;

class CheckoutCompletedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $object = (array) data_get($payload, 'data.object', []);

        $providerPaymentId = (string) data_get($object, 'payment_id', data_get($object, 'payment_intent', ''));

        if ($providerPaymentId === '') {
            return;
        }

        $user = $this->resolveUser($object);

        if (! $user) {
            return;
        }

        Payment::query()->updateOrCreate([
            'provider' => $event->provider,
            'provider_payment_id' => $providerPaymentId,
        ], [
            'user_id' => $user->id,
            'status' => 'succeeded',
            'amount' => (int) data_get($object, 'amount_total', data_get($object, 'amount', 0)),
            'currency' => strtoupper((string) data_get($object, 'currency', 'USD')),
            'paid_at' => now(),
            'metadata' => [
                'event_id' => $event->external_event_id,
                'checkout_session_id' => (string) data_get($object, 'id', ''),
            ],
        ]);
    }

    private function resolveUser(array $object): ?User
    {
        $userId = data_get($object, 'metadata.user_id', data_get($object, 'user_id'));

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }
}

