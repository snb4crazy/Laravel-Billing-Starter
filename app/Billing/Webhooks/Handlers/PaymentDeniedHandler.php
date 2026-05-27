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
        $details = (array) (data_get($payload, 'resource') ?? data_get($payload, 'data.object', []));

        $providerPaymentId = (string) data_get($details, 'id', '');

        if ($providerPaymentId === '') {
            return;
        }

        $user = $this->resolveUser($details);

        if (! $user) {
            return;
        }

        $amount = $this->resolveAmount($details);
        $currency = (string) data_get($details, 'amount.currency_code', data_get($details, 'currency', 'USD'));
        $reason = (string) (data_get($details, 'status_details.reason')
            ?? data_get($details, 'failure_code')
            ?? data_get($details, 'failure_message')
            ?? data_get($details, 'outcome.seller_message')
            ?? 'PAYMENT_FAILED');

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
                'status_details' => (array) data_get($details, 'status_details', []),
            ],
        ]);
    }

    private function extractPaymentDetails(array $payload): array
    {
        return (array) (data_get($payload, 'resource') ?? data_get($payload, 'data.object', []));
    }

    private function resolveUser(array $resource): ?User
    {
        $candidates = [
            // Stripe-style payloads (charge.*)
            data_get($resource, 'metadata.user_id'),
            data_get($resource, 'user_id'),

            // PayPal-style payloads (PAYMENT.CAPTURE.*)
            data_get($resource, 'custom_id'),
            data_get($resource, 'supplementary_data.related_ids.order_id'),
        ];

        foreach ($candidates as $userId) {
            if (is_numeric($userId)) {
                return User::query()->find((int) $userId);
            }
        }

        return null;
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

