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
            match ($event->event_type_canonical) {
                'checkout.completed' => $this->checkoutCompletedHandler->handle($event),
                'invoice.paid' => $this->invoicePaidHandler->handle($event),
                'invoice.payment_failed' => $this->invoicePaymentFailedHandler->handle($event),
                'subscription.canceled' => $this->subscriptionCanceledHandler->handle($event),
                default => null,
            };

            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
            ])->save();
        });
    }
}

