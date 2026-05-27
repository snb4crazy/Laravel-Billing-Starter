<?php

namespace Tests\Unit;

use App\Billing\Contracts\StripeClientInterface;
use App\Billing\Providers\StripeProvider;
use App\Models\Plan;
use App\Models\User;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class StripeProviderTest extends TestCase
{
    // -----------------------------------------------------------------------
    // createCheckoutSession — subscription plan
    // -----------------------------------------------------------------------

    public function test_create_checkout_session_subscription_mode(): void
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'alice@example.com';

        $plan = new Plan();
        $plan->provider_plan_monthly_id = 'price_monthly_001';
        $plan->provider_plan_yearly_id = 'price_yearly_001';

        /** @var MockInterface&StripeClientInterface $client */
        $client = Mockery::mock(StripeClientInterface::class);

        $client->shouldReceive('findOrCreateCustomer')
            ->once()
            ->with('alice@example.com', ['user_id' => '1'])
            ->andReturn(['id' => 'cus_test_001']);

        $client->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => (
                $params['customer'] === 'cus_test_001'
                && $params['mode'] === 'subscription'
                && $params['line_items'][0]['price'] === 'price_monthly_001'
                && $params['success_url'] === 'https://example.com/success'
                && $params['cancel_url'] === 'https://example.com/cancel'
                && $params['metadata']['user_id'] === '1'
            )))
            ->andReturn(['id' => 'cs_test_001', 'url' => 'https://checkout.stripe.com/cs_test_001']);

        $provider = new StripeProvider($client);

        $result = $provider->createCheckoutSession($user, $plan, [
            'interval' => 'monthly',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $this->assertEquals('cs_test_001', $result['session_id']);
        $this->assertEquals('https://checkout.stripe.com/cs_test_001', $result['checkout_url']);
    }
    
    // -----------------------------------------------------------------------
    // createCheckoutSession — yearly interval uses yearly price ID
    // -----------------------------------------------------------------------
    
    public function test_create_checkout_session_uses_yearly_price_id(): void
    {
        $user = new User();
        $user->id = 2;
        $user->email = 'bob@example.com';
        
        $plan = new Plan();
        $plan->provider_plan_monthly_id = 'price_monthly_002';
        $plan->provider_plan_yearly_id = 'price_yearly_002';
        
        /** @var MockInterface&StripeClientInterface $client */
        $client = Mockery::mock(StripeClientInterface::class);
        
        $client->shouldReceive('findOrCreateCustomer')->andReturn(['id' => 'cus_test_002']);
        
        $client->shouldReceive('createCheckoutSession')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => (
                $params['line_items'][0]['price'] === 'price_yearly_002'
            )))
            ->andReturn(['id' => 'cs_test_002', 'url' => 'https://checkout.stripe.com/cs_test_002']);
        
        (new StripeProvider($client))->createCheckoutSession($user, $plan, [
            'interval' => 'yearly',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);
    }
    
    
}

