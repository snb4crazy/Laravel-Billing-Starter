<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Subscription;
use App\Models\WebhookEvent;

class SubscriptionActivatedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        
        // PayPal event structure: resource.id contains the subscription ID
        $resource = (array) data_get($payload, 'resource', []);
        $providerSubscriptionId = (string) data_get($resource, 'id', '');

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
            'status' => 'active',
        ])->save();
    }
}


