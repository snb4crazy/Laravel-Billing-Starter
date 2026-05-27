<?php

namespace App\Billing\Webhooks;

use App\Billing\Webhooks\Handlers\CheckoutCompletedHandler;
use App\Billing\Webhooks\Handlers\InvoicePaidHandler;
use App\Billing\Webhooks\Handlers\InvoicePaymentFailedHandler;
use App\Billing\Webhooks\Handlers\SubscriptionCanceledHandler;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

class WebhookEventProcessor
{
    public function __construct(
        private readonly CheckoutCompletedHandler $checkoutCompletedHandler,
        private readonly InvoicePaidHandler $invoicePaidHandler,
        private readonly InvoicePaymentFailedHandler $invoicePaymentFailedHandler,
        private readonly SubscriptionCanceledHandler $subscriptionCanceledHandler,
    ) {
    }

    public function process(WebhookEvent $event): void
    {
        DB::transaction(function () use ($event): void {
            $handled = match ($event->event_type_canonical) {
                'checkout.completed' => tap(true, fn () => $this->checkoutCompletedHandler->handle($event)),
                'invoice.paid' => tap(true, fn () => $this->invoicePaidHandler->handle($event)),
                'invoice.payment_failed' => tap(true, fn () => $this->invoicePaymentFailedHandler->handle($event)),
                'subscription.canceled' => tap(true, fn () => $this->subscriptionCanceledHandler->handle($event)),
                default => false,
            };

            if ($handled) {
                $event->forceFill([
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                    'failure_reason' => null,
                ])->save();

                return;
            }

            $event->forceFill([
                'processing_status' => 'ignored',
                'failure_reason' => 'No webhook handler implemented for canonical event type: '.(string) $event->event_type_canonical,
                'processed_at' => now(),
            ])->save();
        });
    }
}

