<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Contracts\PaddleClientInterface;
use App\Models\Plan;
use App\Models\User;
use RuntimeException;

/**
 * Paddle implementation of BillingProvider.
 *
 * Paddle notes:
 * - Paddle uses price IDs (not plan IDs like PayPal/Stripe)
 * - Checkouts are created with a price_id that can be one-time or recurring
 * - Subscriptions are created directly with a price_id for recurring billing
 * - Tax handling is automatic (Paddle is merchant of record)
 *
 * Extraction note:
 * - Copy this file and the Paddle client contract/implementation files.
 * - Bind PaddleClientInterface in your AppServiceProvider.
 * - Add provider config keys in config/billing.php and env vars.
 */
class PaddleProvider implements BillingProvider
{
    public function __construct(private readonly PaddleClientInterface $paddle)
    {
    }

    public function createCheckoutSession(User $user, ?Plan $plan, array $options = []): array
    {
        $priceId = (string) ($options['price_id'] ?? $plan?->provider_plan_monthly_id ?? '');

        if ($priceId === '') {
            throw new RuntimeException('Paddle checkout requires a price_id.');
        }

        $checkout = $this->paddle->createCheckout([
            'price_id' => $priceId,
            'customer_email' => $user->email,
            'success_url' => (string) ($options['success_url'] ?? config('app.url')),
            'custom_data' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        return [
            'session_id' => $checkout['id'],
            'checkout_url' => $checkout['url'],
        ];
    }

    public function createSubscription(User $user, Plan $plan, string $interval): array
    {
        $priceId = $interval === 'yearly'
            ? $plan->provider_plan_yearly_id
            : $plan->provider_plan_monthly_id;

        if (! is_string($priceId) || $priceId === '') {
            throw new RuntimeException('Paddle subscription requires a provider price ID for the selected interval.');
        }

        $subscription = $this->paddle->createSubscription([
            'price_id' => $priceId,
            'customer_email' => $user->email,
            'custom_data' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        return [
            'provider_subscription_id' => $subscription['id'],
            'status' => strtolower((string) $subscription['status']),
        ];
    }
}

