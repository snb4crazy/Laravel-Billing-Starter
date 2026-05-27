<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;

class InvoicePaymentFailedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $object = (array) data_get($payload, 'data.object', []);

        $providerInvoiceId = (string) data_get($object, 'id', '');

        if ($providerInvoiceId === '') {
            return;
        }

        $user = $this->resolveUser($object);

        if (! $user) {
            return;
        }

        $subscription = $this->resolveSubscription($event->provider, $object);

        Invoice::query()->updateOrCreate([
            'provider' => $event->provider,
            'provider_invoice_id' => $providerInvoiceId,
        ], [
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'invoice_number' => data_get($object, 'number'),
            'status' => 'uncollectible',
            'amount_due' => (int) data_get($object, 'amount_due', 0),
            'amount_paid' => (int) data_get($object, 'amount_paid', 0),
            'currency' => strtoupper((string) data_get($object, 'currency', 'USD')),
            'metadata' => [
                'event_id' => $event->external_event_id,
            ],
        ]);

        if ($subscription && $subscription->status !== 'canceled') {
            $subscription->forceFill(['status' => 'past_due'])->save();
        }
    }

    private function resolveUser(array $object): ?User
    {
        $userId = data_get($object, 'metadata.user_id', data_get($object, 'user_id'));

        if (! is_numeric($userId)) {
            return null;
        }

        return User::query()->find((int) $userId);
    }

    private function resolveSubscription(string $provider, array $object): ?Subscription
    {
        $providerSubscriptionId = (string) data_get($object, 'subscription', '');

        if ($providerSubscriptionId === '') {
            return null;
        }

        return Subscription::query()
            ->where('provider', $provider)
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();
    }
}

