<?php

namespace Tests\Unit;

use App\Billing\Contracts\PayPalClientInterface;
use App\Billing\Providers\PayPalProvider;
use App\Models\Plan;
use App\Models\User;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PayPalProviderTest extends TestCase
{
    public function test_create_checkout_session_uses_order_api_and_returns_approval_url(): void
    {
        $user = new User();
        $user->id = 10;
        $user->name = 'Alice Doe';
        $user->email = 'alice@example.com';

        $plan = new Plan();
        $plan->name = 'Starter';
        $plan->slug = 'starter';
        $plan->currency = 'USD';
        $plan->monthly_price = 1900;

        /** @var MockInterface&PayPalClientInterface $client */
        $client = Mockery::mock(PayPalClientInterface::class);

        $client->shouldReceive('createCheckoutOrder')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => (
                $params['intent'] === 'CAPTURE'
                && $params['purchase_units'][0]['custom_id'] === '10'
                && $params['purchase_units'][0]['amount']['value'] === '19.00'
                && $params['application_context']['return_url'] === 'https://example.com/success'
            )))
            ->andReturn([
                'id' => 'ORDER-123',
                'status' => 'CREATED',
                'approve_url' => 'https://paypal.test/approve/ORDER-123',
            ]);

        $provider = new PayPalProvider($client);

        $result = $provider->createCheckoutSession($user, $plan, [
            'interval' => 'monthly',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $this->assertSame('ORDER-123', $result['session_id']);
        $this->assertSame('https://paypal.test/approve/ORDER-123', $result['checkout_url']);
    }

    public function test_create_subscription_uses_interval_specific_paypal_plan_id(): void
    {
        $user = new User();
        $user->id = 11;
        $user->name = 'Bob Doe';
        $user->email = 'bob@example.com';
        
        $plan = new Plan();
        $plan->provider_plan_monthly_id = 'P-MONTHLY-001';
        $plan->provider_plan_yearly_id = 'P-YEARLY-001';
        
        /** @var MockInterface&PayPalClientInterface $client */
        $client = Mockery::mock(PayPalClientInterface::class);
        
        $client->shouldReceive('createSubscription')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => (
                $params['plan_id'] === 'P-YEARLY-001'
                && $params['custom_id'] === '11'
                && $params['subscriber']['email_address'] === 'bob@example.com'
            )))
            ->andReturn([
                'id' => 'I-SUB-001',
                'status' => 'APPROVAL_PENDING',
                'approve_url' => 'https://paypal.test/approve/I-SUB-001',
            ]);
        
        $provider = new PayPalProvider($client);
        $result = $provider->createSubscription($user, $plan, 'yearly');
        
        $this->assertSame('I-SUB-001', $result['provider_subscription_id']);
        $this->assertSame('approval_pending', $result['status']);
    }
}

