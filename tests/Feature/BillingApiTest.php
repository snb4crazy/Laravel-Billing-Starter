<?php

namespace Tests\Feature;

use App\Billing\Contracts\PayPalClientInterface;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Auth
    // -----------------------------------------------------------------------

    public function test_user_can_register_and_receive_bearer_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'Secret1234!ok',
            'password_confirmation' => 'Secret1234!ok',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type'],
            ]);
    }
    
    public function test_user_can_login_and_receive_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'bob@example.com',
            'password' => bcrypt('Secret1234!ok'),
        ]);
        
        $response = $this->postJson('/api/auth/login', [
            'email' => 'bob@example.com',
            'password' => 'Secret1234!ok',
        ]);
        
        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['user', 'token', 'token_type'],
            ]);
    }
    
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'carol@example.com']);
        
        $this->postJson('/api/auth/login', [
            'email' => 'carol@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
    
    public function test_me_requires_bearer_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
    
    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
    
    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;
        
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        
        // Confirm the token record was deleted from the database.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
    
    // -----------------------------------------------------------------------
    // Billing
    // -----------------------------------------------------------------------
    
    public function test_unauthenticated_request_to_billing_plans_is_rejected(): void
    {
        $this->getJson('/api/billing/plans')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_active_plans(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        Plan::query()->create([
            'name' => 'Starter',
            'slug' => 'starter',
            'monthly_price' => 1000,
            'currency' => 'USD',
            'provider' => 'stripe',
            'is_active' => true,
        ]);

        Plan::query()->create([
            'name' => 'Archived',
            'slug' => 'archived',
            'monthly_price' => 500,
            'currency' => 'USD',
            'provider' => 'stripe',
            'is_active' => false,
        ]);

        $this->withToken($token)
            ->getJson('/api/billing/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_subscription_create_requires_unique_idempotency_key_per_payload(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $plan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'monthly_price' => 2000,
            'currency' => 'USD',
            'provider' => 'stripe',
            'is_active' => true,
        ]);

        $payload = [
            'plan_id' => $plan->id,
            'interval' => 'monthly',
        ];

        $headers = ['Idempotency-Key' => 'idem-key-sub-123'];

        $first = $this->withToken($token)->postJson('/api/billing/subscriptions', $payload, $headers);
        $second = $this->withToken($token)->postJson('/api/billing/subscriptions', $payload, $headers);

        $first->assertCreated();
        $second->assertConflict();
    }
    
    public function test_idempotency_key_is_not_consumed_when_mutation_returns_4xx(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        
        $subscription = Subscription::query()->create([
            'user_id' => $owner->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_owner_002',
            'status' => 'active',
        ]);
        
        $token = $otherUser->createToken('api')->plainTextToken;
        $headers = ['Idempotency-Key' => 'idem-cancel-foreign-002'];
        
        $first = $this->withToken($token)
            ->postJson('/api/billing/subscriptions/'.$subscription->id.'/cancel', [], $headers);
        
        $second = $this->withToken($token)
            ->postJson('/api/billing/subscriptions/'.$subscription->id.'/cancel', [], $headers);
        
        $first->assertForbidden();
        $second->assertForbidden();
    }

    public function test_user_cannot_cancel_another_users_subscription(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $subscription = Subscription::query()->create([
            'user_id' => $owner->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_owner_001',
            'status' => 'active',
        ]);

        $token = $otherUser->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/billing/subscriptions/'.$subscription->id.'/cancel', [], [
                'Idempotency-Key' => 'idem-cancel-foreign-001',
            ])
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Webhook domain processing
    // -----------------------------------------------------------------------

    /**
     * Build a Stripe-compatible Stripe-Signature header value.
     * Mirrors how Stripe signs events: HMAC-SHA256( "{timestamp}.{body}", secret )
     */
    private function makeStripeSignatureHeader(string $rawPayload, string $secret): string
    {
        $timestamp = now()->timestamp;
        $sig = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);

        return "t={$timestamp},v1={$sig}";
    }

    private function sendStripeWebhook(array $payload, string $secret = 'whsec_test_secret'): \Illuminate\Testing\TestResponse
    {
        config()->set('billing.webhooks.providers.stripe.signing_secret', $secret);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->postJson(
            '/api/billing/webhooks/stripe',
            $payload,
            ['Stripe-Signature' => $this->makeStripeSignatureHeader($raw, $secret)],
        );
    }

    private function fakePayPalWebhookVerification(): void
    {
        $this->app->bind(PayPalClientInterface::class, function (): PayPalClientInterface {
            return new class implements PayPalClientInterface
            {
                public function createCheckoutOrder(array $params): array
                {
                    return ['id' => 'ORDER-FAKE', 'status' => 'CREATED', 'approve_url' => 'https://example.test'];
                }

                public function createSubscription(array $params): array
                {
                    return ['id' => 'SUB-FAKE', 'status' => 'APPROVAL_PENDING', 'approve_url' => 'https://example.test'];
                }

                public function verifyWebhookSignature(array $params): bool
                {
                    return true;
                }
            };
        });
    }

    private function sendPayPalWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        config()->set('billing.providers.paypal.client_id', 'paypal-client-id-test');
        config()->set('billing.providers.paypal.secret', 'paypal-client-secret-test');
        config()->set('billing.webhooks.providers.paypal.signing_secret', 'WH-TEST-ID');
        $this->fakePayPalWebhookVerification();

        return $this->postJson(
            '/api/billing/webhooks/paypal',
            $payload,
            [
                'Paypal-Transmission-Id' => 'tx-001',
                'Paypal-Transmission-Time' => now()->toIso8601String(),
                'Paypal-Cert-Url' => 'https://api-m.sandbox.paypal.com/certs/cert.pem',
                'Paypal-Auth-Algo' => 'SHA256withRSA',
                'Paypal-Transmission-Sig' => 'stub-signature',
            ],
        );
    }

    public function test_webhook_signature_is_verified_and_duplicate_events_are_deduped(): void
    {
        $payload = ['id' => 'evt_dedup_001', 'type' => 'invoice.paid'];

        $this->sendStripeWebhook($payload)->assertCreated();
        $this->sendStripeWebhook($payload)->assertOk()->assertJson(['message' => 'Duplicate event ignored.']);
    }

    public function test_webhook_rejects_invalid_stripe_signature(): void
    {
        config()->set('billing.webhooks.providers.stripe.signing_secret', 'whsec_test_secret');

        $this->postJson(
            '/api/billing/webhooks/stripe',
            ['id' => 'evt_bad_sig', 'type' => 'invoice.paid'],
            ['Stripe-Signature' => 't='.now()->timestamp.',v1=invalidsignature'],
        )->assertUnauthorized();
    }

    public function test_stripe_charge_succeeded_webhook_creates_payment(): void
    {
        $user = User::factory()->create();
        
        $payload = [
            'id' => 'evt_charge_succeeded_001',
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'id' => 'ch_001',
                    'amount' => 2500,
                    'currency' => 'usd',
                    'metadata' => ['user_id' => (string) $user->id],
                ],
            ],
        ];
        
        $this->sendStripeWebhook($payload)->assertCreated();
        
        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_payment_id' => 'ch_001',
            'user_id' => $user->id,
            'status' => 'succeeded',
            'amount' => 2500,
            'currency' => 'USD',
        ]);
    }

    public function test_stripe_charge_failed_webhook_creates_failed_payment(): void
    {
        $user = User::factory()->create();
        
        $payload = [
            'id' => 'evt_charge_failed_001',
            'type' => 'charge.failed',
            'data' => [
                'object' => [
                    'id' => 'ch_002',
                    'amount' => 2500,
                    'currency' => 'usd',
                    'metadata' => ['user_id' => (string) $user->id],
                    'failure_code' => 'card_declined',
                ],
            ],
        ];
        
        $this->sendStripeWebhook($payload)->assertCreated();
        
        $this->assertDatabaseHas('payments', [
            'provider' => 'stripe',
            'provider_payment_id' => 'ch_002',
            'user_id' => $user->id,
            'status' => 'failed',
            'amount' => 2500,
            'currency' => 'USD',
        ]);
        
        $payment = Payment::query()->where('provider_payment_id', 'ch_002')->first();
        $this->assertSame('card_declined', $payment?->metadata['failure_reason']);
    }

    public function test_paypal_subscription_cancelled_webhook_cancels_subscription(): void
    {
        $user = User::factory()->create();
        
        Subscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_subscription_id' => 'I-SUB-CANCEL-001',
            'status' => 'active',
        ]);
        
        $payload = [
            'id' => 'WH-SUB-CANCEL-001',
            'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'resource' => [
                'id' => 'I-SUB-CANCEL-001',
            ],
        ];
        
        $this->sendPayPalWebhook($payload)->assertCreated();
        
        $this->assertDatabaseHas('subscriptions', [
            'provider' => 'paypal',
            'provider_subscription_id' => 'I-SUB-CANCEL-001',
            'status' => 'canceled',
        ]);
    }

    public function test_paypal_webhook_accepts_event_type_field(): void
    {
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_subscription_id' => 'I-SUB-CANCEL-002',
            'status' => 'active',
        ]);

        $payload = [
            'id' => 'WH-SUB-CANCEL-002',
            'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'resource' => [
                'id' => 'I-SUB-CANCEL-002',
            ],
        ];

        $this->sendPayPalWebhook($payload)->assertCreated();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'paypal',
            'external_event_id' => 'WH-SUB-CANCEL-002',
            'event_type_raw' => 'BILLING.SUBSCRIPTION.CANCELLED',
            'event_type_canonical' => 'subscription.canceled',
            'processing_status' => 'processed',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'provider' => 'paypal',
            'provider_subscription_id' => 'I-SUB-CANCEL-002',
            'status' => 'canceled',
        ]);
    }

    public function test_paypal_webhook_is_rejected_when_paypal_credentials_are_missing(): void
    {
        config()->set('billing.providers.paypal.client_id', '');
        config()->set('billing.providers.paypal.secret', '');
        config()->set('billing.webhooks.providers.paypal.signing_secret', 'WH-TEST-ID');

        $this->postJson(
            '/api/billing/webhooks/paypal',
            ['id' => 'WH-EVT-001', 'type' => 'PAYMENT.CAPTURE.COMPLETED'],
            [
                'Paypal-Transmission-Id' => 'tx-001',
                'Paypal-Transmission-Time' => now()->toIso8601String(),
                'Paypal-Cert-Url' => 'https://api-m.sandbox.paypal.com/certs/cert.pem',
                'Paypal-Auth-Algo' => 'SHA256withRSA',
                'Paypal-Transmission-Sig' => 'stub-signature',
            ],
        )
            ->assertServiceUnavailable()
            ->assertJson([
                'message' => 'PayPal webhook verification is unavailable until API credentials are configured.',
            ]);
    }


    public function test_unhandled_canonical_webhook_event_is_marked_ignored(): void
    {
        $payload = [
            'id' => 'evt_unhandled_001',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_unknown_001',
                ],
            ],
        ];

        $this->sendStripeWebhook($payload)->assertCreated();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe',
            'external_event_id' => 'evt_unhandled_001',
            'event_type_canonical' => 'subscription.updated',
            'processing_status' => 'ignored',
        ]);
    }

    public function test_invoice_paid_webhook_creates_paid_invoice_and_activates_subscription(): void
    {
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_paid_001',
            'status' => 'incomplete',
        ]);

        $payload = [
            'id' => 'evt_invoice_paid_001',
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'id' => 'in_paid_001',
                    'number' => 'INV-1001',
                    'amount_due' => 2000,
                    'amount_paid' => 2000,
                    'currency' => 'usd',
                    'subscription' => 'sub_paid_001',
                    'metadata' => ['user_id' => (string) $user->id],
                ],
            ],
        ];

        $this->sendStripeWebhook($payload)->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'provider_invoice_id' => 'in_paid_001',
            'status' => 'paid',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_paid_001',
            'status' => 'active',
        ]);
    }

    public function test_invoice_payment_failed_webhook_marks_subscription_past_due(): void
    {
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_failed_001',
            'status' => 'active',
        ]);

        $payload = [
            'id' => 'evt_invoice_failed_001',
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_failed_001',
                    'number' => 'INV-1002',
                    'amount_due' => 2000,
                    'currency' => 'usd',
                    'subscription' => 'sub_failed_001',
                    'metadata' => ['user_id' => (string) $user->id],
                ],
            ],
        ];

        $this->sendStripeWebhook($payload)->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_invoice_id' => 'in_failed_001',
            'status' => 'uncollectible',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_failed_001',
            'status' => 'past_due',
        ]);
    }

    public function test_subscription_deleted_webhook_marks_subscription_canceled(): void
    {
        $user = User::factory()->create();

        Subscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_cancelled_001',
            'status' => 'active',
        ]);

        $payload = [
            'id' => 'evt_subscription_deleted_001',
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_cancelled_001']],
        ];

        $this->sendStripeWebhook($payload)->assertCreated();

        $this->assertDatabaseHas('subscriptions', [
            'provider_subscription_id' => 'sub_cancelled_001',
            'status' => 'canceled',
        ]);
    }
}

