<?php

namespace App\Billing\Providers;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Contracts\StripeClientInterface;
use App\Models\Plan;
use App\Models\User;

/**
 * Stripe implementation of BillingProvider.
 *
 * Extraction note:
 *   Copy this class plus BillingProvider, StripeClientInterface, StripeHttpClient
 *   into any Laravel app. Register StripeHttpClient as the StripeClientInterface
 *   binding in AppServiceProvider and wire STRIPE_SECRET_KEY in config.
 *
 * Key design decisions:
 *   - Uses Stripe Checkout (hosted page) for payments and subscriptions.
 *     This keeps the app out of PCI scope for card data.
 *   - Always passes user_id in Stripe metadata so webhooks can map back
 *     to the correct user without guessing.
 *   - Reuses existing Stripe Customer by email lookup before creating.
 */
class StripeProvider implements BillingProvider
{
    public function __construct(private readonly StripeClientInterface $stripe)
    {
    }

    /**
     * Create a hosted Stripe Checkout Session.
     *
     * For subscriptions: pass `plan_id` in $options and the plan model as $plan.
     * For one-time payments: pass `amount` and `currency` in $options, $plan = null.
     *
     * @param  array{
     *   success_url:string,
     *   cancel_url:string,
     *   interval?:string,
     *   amount?:int,
     *   currency?:string,
     *   plan_id?:int,
     * }  $options
     * @return array{session_id:string,checkout_url:string}
     */
    public function createCheckoutSession(User $user, ?Plan $plan, array $options = []): array
    {
        $customer = $this->stripe->findOrCreateCustomer($user->email, [
            'user_id' => (string) $user->id,
        ]);

        $params = [
            'customer' => $customer['id'],
            'success_url' => $options['success_url'],
            'cancel_url' => $options['cancel_url'],
            'client_reference_id' => (string) $user->id,
            'metadata' => ['user_id' => (string) $user->id],
        ];

        if ($plan !== null) {
            $interval = $options['interval'] ?? 'monthly';
            $priceId = $interval === 'yearly'
                ? $plan->provider_plan_yearly_id
                : $plan->provider_plan_monthly_id;

            $params['mode'] = 'subscription';
            $params['line_items'] = [[
                'price' => $priceId,
                'quantity' => 1,
            ]];
            // Session-level metadata is not copied to Stripe Subscription/Invoice.
            // Put user_id into subscription_data metadata for downstream invoice.* handlers.
            $params['subscription_data'] = [
                'metadata' => [
                    'user_id' => (string) $user->id,
                ],
            ];
        } else {
            $params['mode'] = 'payment';
            $params['line_items'] = [[
                'price_data' => [
                    'currency' => strtolower($options['currency'] ?? 'usd'),
                    'product_data' => ['name' => 'One-time payment'],
                    'unit_amount' => (int) ($options['amount'] ?? 0),
                ],
                'quantity' => 1,
            ]];
        }

        $session = $this->stripe->createCheckoutSession($params);

        return [
            'session_id' => $session['id'],
            'checkout_url' => $session['url'],
        ];
    }

    /**
     * Create a Stripe Subscription directly (for programmatic subscription creation
     * where the customer already has a payment method on file).
     *
     * For most browser flows, prefer createCheckoutSession() with mode = subscription.
     *
     * @return array{provider_subscription_id:string,status:string}
     */
    public function createSubscription(User $user, Plan $plan, string $interval = 'monthly'): array
    {
        $customer = $this->stripe->findOrCreateCustomer($user->email, [
            'user_id' => (string) $user->id,
        ]);

        $priceId = $interval === 'yearly'
            ? $plan->provider_plan_yearly_id
            : $plan->provider_plan_monthly_id;

        $result = $this->stripe->createSubscription([
            'customer' => $customer['id'],
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        return [
            'provider_subscription_id' => $result['id'],
            'status' => $result['status'],
        ];
    }
}

