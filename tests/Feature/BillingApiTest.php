<?php

namespace Tests\Feature;

use App\Models\Plan;
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

