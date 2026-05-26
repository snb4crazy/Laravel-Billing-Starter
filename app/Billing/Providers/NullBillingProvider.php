<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\BillingProvider;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Str;

class NullBillingProvider implements BillingProvider
{
    public function createCheckoutSession(User $user, ?Plan $plan, array $options = []): array
    {
        $successUrl = (string) ($options['success_url'] ?? config('app.url'));

        return [
            'session_id' => 'stub_cs_'.Str::ulid(),
            'checkout_url' => rtrim($successUrl, '/').'?stub_checkout=1',
        ];
    }

    public function createSubscription(User $user, Plan $plan, string $interval): array
    {
        return [
            'provider_subscription_id' => 'stub_sub_'.Str::ulid(),
            'status' => 'incomplete',
        ];
    }
}

