<?php

namespace App\Billing\Webhooks\Handlers;

use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;

class PaddleSubscriptionCreatedHandler
{
    public function handle(WebhookEvent $event): void
    {
        $payload = $event->payload_json;
        $data = (array) data_get($payload, 'data', []);

        $subscriptionId = (string) data_get($data, 'id', '');

        if ($subscriptionId === '') {
            return;
        }

        $user = $this->resolveUser($data);

        if (! $user) {
            return;
        }

        Subscription::query()->updateOrCreate([
            'provider' => $event->provider,
            'provider_subscription_id' => $subscriptionId,
        ], [
            'user_id' => $user->id,
            'status' => 'active',
            'metadata' => [
                'event_id' => $event->external_event_id,
                'paddle_customer_id' => (string) data_get($data, 'customer_id', ''),
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

