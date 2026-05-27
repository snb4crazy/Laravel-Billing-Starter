<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Contracts\PayPalClientInterface;
use App\Models\Plan;
use App\Models\User;
use RuntimeException;

/**
 * PayPal implementation of BillingProvider.
 *
 * Extraction note:
 * - Copy this file and the PayPal client contract/implementation files.
 * - Bind PayPalClientInterface in your AppServiceProvider.
 * - Add provider config keys in config/billing.php and env vars.
 */
class PayPalProvider implements BillingProvider
{
    public function __construct(private readonly PayPalClientInterface $payPal)
    {
    }

    public function createCheckoutSession(User $user, ?Plan $plan, array $options = []): array
    {
        $amount = (int) ($options['amount'] ?? $this->resolvePlanAmount($plan, (string) ($options['interval'] ?? 'monthly')));
        $currency = strtoupper((string) ($options['currency'] ?? $plan?->currency ?? 'USD'));

        if ($amount <= 0) {
            throw new RuntimeException('PayPal checkout requires a positive amount.');
        }

        $order = $this->payPal->createCheckoutOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) ($plan?->slug ?? 'one-time'),
                'custom_id' => (string) $user->id,
                'amount' => [
                    'currency_code' => $currency,
                    'value' => number_format($amount / 100, 2, '.', ''),
                ],
                'description' => $plan?->name ?? 'One-time payment',
            ]],
            'application_context' => [
                'return_url' => (string) ($options['success_url'] ?? config('billing.providers.paypal.return_url')),
                'cancel_url' => (string) ($options['cancel_url'] ?? config('billing.providers.paypal.cancel_url')),
                'user_action' => 'PAY_NOW',
            ],
        ]);

        return [
            'session_id' => $order['id'],
            'checkout_url' => $order['approve_url'],
        ];
    }

    public function createSubscription(User $user, Plan $plan, string $interval): array
    {
        $planId = $interval === 'yearly'
            ? $plan->provider_plan_yearly_id
            : $plan->provider_plan_monthly_id;

        if (! is_string($planId) || $planId === '') {
            throw new RuntimeException('PayPal subscription requires a provider plan ID for the selected interval.');
        }

        $subscription = $this->payPal->createSubscription([
            'plan_id' => $planId,
            'custom_id' => (string) $user->id,
            'subscriber' => [
                'email_address' => $user->email,
                'name' => [
                    'given_name' => $this->firstName($user->name),
                    'surname' => $this->lastName($user->name),
                ],
            ],
            'application_context' => [
                'return_url' => (string) config('billing.providers.paypal.return_url'),
                'cancel_url' => (string) config('billing.providers.paypal.cancel_url'),
            ],
        ]);

        return [
            'provider_subscription_id' => $subscription['id'],
            'status' => strtolower((string) $subscription['status']),
        ];
    }

    private function resolvePlanAmount(?Plan $plan, string $interval): int
    {
        if (! $plan) {
            return 0;
        }

        $raw = $interval === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        return (int) ($raw ?? 0);
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return $parts[0] ?? 'Customer';
    }

    private function lastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) <= 1) {
            return 'User';
        }

        return (string) end($parts);
    }
}

