<?php

namespace Tests\Unit;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Billing\Webhooks\Handlers\PaymentCompletedHandler;
use App\Billing\Webhooks\Handlers\PaymentDeniedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionActivatedHandler;
use Tests\TestCase;

class PayPalWebhookHandlersTest extends TestCase
{
    public function test_payment_completed_handler_creates_succeeded_payment(): void
    {
        $user = User::factory()->create();

        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-001',
            'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
            'event_type_canonical' => 'payment.succeeded',
            'payload_json' => [
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'amount' => [
                        'value' => '19.99',
                        'currency_code' => 'USD',
                    ],
                    'custom_id' => (string) $user->id,
                    'supplementary_data' => [
                        'related_ids' => [
                            'order_id' => 'ORDER-123',
                        ],
                    ],
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);

        $handler = new PaymentCompletedHandler();
        $handler->handle($event);

        $this->assertDatabaseHas('payments', [
            'provider' => 'paypal',
            'provider_payment_id' => 'CAPTURE-123',
            'user_id' => $user->id,
            'status' => 'succeeded',
            'amount' => 1999,
            'currency' => 'USD',
        ]);
    }

    public function test_payment_completed_handler_updates_existing_payment(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'provider' => 'paypal',
            'provider_payment_id' => 'CAPTURE-123',
            'user_id' => $user->id,
            'status' => 'pending',
            'amount' => 1999,
            'currency' => 'USD',
        ]);
        
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-001',
            'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
            'event_type_canonical' => 'payment.succeeded',
            'payload_json' => [
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'amount' => [
                        'value' => '19.99',
                        'currency_code' => 'USD',
                    ],
                    'custom_id' => (string) $user->id,
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new PaymentCompletedHandler();
        $handler->handle($event);
        
        $this->assertSame(1, Payment::count());
        $payment->refresh();
        $this->assertSame('succeeded', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }
    
    public function test_payment_completed_handler_skips_without_capture_id(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-001',
            'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
            'event_type_canonical' => 'payment.succeeded',
            'payload_json' => [
                'resource' => [
                    'id' => '',
                    'amount' => [
                        'value' => '19.99',
                        'currency_code' => 'USD',
                    ],
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new PaymentCompletedHandler();
        $handler->handle($event);
        
        $this->assertDatabaseCount('payments', 0);
    }
    
    public function test_payment_completed_handler_skips_without_user(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-001',
            'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
            'event_type_canonical' => 'payment.succeeded',
            'payload_json' => [
                'resource' => [
                    'id' => 'CAPTURE-123',
                    'amount' => [
                        'value' => '19.99',
                        'currency_code' => 'USD',
                    ],
                    'custom_id' => '99999',
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new PaymentCompletedHandler();
        $handler->handle($event);
        
        $this->assertDatabaseCount('payments', 0);
    }
    
    public function test_payment_denied_handler_creates_failed_payment(): void
    {
        $user = User::factory()->create();
        
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-DENIED',
            'event_type_raw' => 'PAYMENT.CAPTURE.DENIED',
            'event_type_canonical' => 'payment.failed',
            'payload_json' => [
                'resource' => [
                    'id' => 'CAPTURE-456',
                    'amount' => [
                        'value' => '29.99',
                        'currency_code' => 'USD',
                    ],
                    'custom_id' => (string) $user->id,
                    'status_details' => [
                        'reason' => 'INSUFFICIENT_FUNDS',
                    ],
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new PaymentDeniedHandler();
        $handler->handle($event);
        
        $this->assertDatabaseHas('payments', [
            'provider' => 'paypal',
            'provider_payment_id' => 'CAPTURE-456',
            'user_id' => $user->id,
            'status' => 'failed',
            'amount' => 2999,
            'currency' => 'USD',
        ]);
    }
    
    public function test_payment_denied_handler_stores_failure_reason(): void
    {
        $user = User::factory()->create();
        
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-CAPTURE-DENIED',
            'event_type_raw' => 'PAYMENT.CAPTURE.DENIED',
            'event_type_canonical' => 'payment.failed',
            'payload_json' => [
                'resource' => [
                    'id' => 'CAPTURE-456',
                    'amount' => [
                        'value' => '29.99',
                        'currency_code' => 'USD',
                    ],
                    'custom_id' => (string) $user->id,
                    'status_details' => [
                        'reason' => 'INSUFFICIENT_FUNDS',
                    ],
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new PaymentDeniedHandler();
        $handler->handle($event);
        
        $payment = Payment::where('provider_payment_id', 'CAPTURE-456')->first();
        $this->assertSame('INSUFFICIENT_FUNDS', $payment->metadata['failure_reason']);
    }
    
    public function test_subscription_activated_handler_updates_subscription_status(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_subscription_id' => 'I-SUB-001',
            'status' => 'incomplete',
        ]);
        
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-SUB-ACTIVATED',
            'event_type_raw' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'event_type_canonical' => 'subscription.activated',
            'payload_json' => [
                'resource' => [
                    'id' => 'I-SUB-001',
                    'status' => 'ACTIVE',
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new SubscriptionActivatedHandler();
        $handler->handle($event);
        
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
    }
    
    public function test_subscription_activated_handler_skips_without_subscription_id(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-SUB-ACTIVATED',
            'event_type_raw' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'event_type_canonical' => 'subscription.activated',
            'payload_json' => [
                'resource' => [
                    'id' => '',
                    'status' => 'ACTIVE',
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new SubscriptionActivatedHandler();
        $handler->handle($event);
        
        // Should not throw any errors, just skip silently
        $this->assertTrue(true);
    }
    
    public function test_subscription_activated_handler_skips_if_subscription_not_found(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'paypal',
            'external_event_id' => 'WH-SUB-ACTIVATED',
            'event_type_raw' => 'BILLING.SUBSCRIPTION.ACTIVATED',
            'event_type_canonical' => 'subscription.activated',
            'payload_json' => [
                'resource' => [
                    'id' => 'I-SUB-NOTFOUND',
                    'status' => 'ACTIVE',
                ],
            ],
            'headers_json' => [],
            'signature_verified_at' => now(),
            'processing_status' => 'pending',
        ]);
        
        $handler = new SubscriptionActivatedHandler();
        $handler->handle($event);
        
        // Should not throw any errors, just skip silently
        $this->assertTrue(true);
    }
}

