<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Subscription;
use App\Models\WebhookEvent;

class SubscriptionCanceledHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $object = (array) (data_get($payload, 'data.object') ?? data_get($payload, 'resource', []));

        $providerSubscriptionId = (string) data_get($object, 'id', data_get($object, 'subscription', ''));

        if ($providerSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('provider', $event->provider)
            ->where('provider_subscription_id', $providerSubscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'status' => 'canceled',
            'canceled_at' => now(),
        ])->save();
    }
}

