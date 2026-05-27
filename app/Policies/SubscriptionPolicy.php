<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin() || $subscription->user_id === $user->id;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $this->view($user, $subscription);
    }
}

