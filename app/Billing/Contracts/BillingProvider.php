<?php

namespace App\Billing\Contracts;

use App\Models\Plan;
use App\Models\User;

interface BillingProvider
{
    /**
     * @return array{session_id:string,checkout_url:string}
     */
    public function createCheckoutSession(User $user, ?Plan $plan, array $options = []): array;

    /**
     * @return array{provider_subscription_id:string,status:string}
     */
    public function createSubscription(User $user, Plan $plan, string $interval): array;
}

