<?php

namespace Tests\Feature;

use App\Models\Plan;
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

    public function test_webhook_signature_is_verified_and_duplicate_events_are_deduped(): void
    {
        config()->set('billing.webhooks.providers.stripe.signing_secret', 'whsec_test_secret');

        $payload = [
            'id' => 'evt_test_456',
            'type' => 'invoice.paid',
        ];

        $timestamp = now()->timestamp;
        $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, 'whsec_test_secret');

        $headers = [
            'X-Billing-Timestamp' => (string) $timestamp,
            'X-Billing-Signature' => $signature,
        ];

        $first = $this->postJson('/api/billing/webhooks/stripe', $payload, $headers);
        $second = $this->postJson('/api/billing/webhooks/stripe', $payload, $headers);

        $first->assertCreated();
        $second->assertOk()->assertJson([
            'message' => 'Duplicate event ignored.',
        ]);
    }
}

