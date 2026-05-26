<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_active_plans(): void
    {
        $user = User::factory()->create();

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

        $response = $this->actingAs($user)->getJson('/api/billing/plans');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    
}

