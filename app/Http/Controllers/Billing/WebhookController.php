<?php

namespace App\Http\Controllers\Billing;

use App\Billing\Webhooks\WebhookEventProcessor;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    private const EVENT_MAP = [
        // Stripe events
        'checkout.session.completed' => 'checkout.completed',
        'invoice.paid' => 'invoice.paid',
        'invoice.payment_failed' => 'invoice.payment_failed',
        'customer.subscription.created' => 'subscription.created',
        'customer.subscription.updated' => 'subscription.updated',
        'customer.subscription.deleted' => 'subscription.canceled',
        'charge.succeeded' => 'payment.succeeded',
        'charge.failed' => 'payment.failed',
        // PayPal events
        'PAYMENT.CAPTURE.COMPLETED' => 'payment.succeeded',
        'PAYMENT.CAPTURE.DENIED' => 'payment.failed',
        'BILLING.SUBSCRIPTION.ACTIVATED' => 'subscription.activated',
        'BILLING.SUBSCRIPTION.CANCELLED' => 'subscription.canceled',
    ];

    public function handle(Request $request, string $provider, WebhookEventProcessor $processor): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255', 'required_without:event_type'],
            'event_type' => ['nullable', 'string', 'max:255', 'required_without:type'],
        ]);

        $eventTypeRaw = (string) ($payload['type'] ?? $payload['event_type']);

        $canonicalType = self::EVENT_MAP[$eventTypeRaw] ?? null;

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'external_event_id' => $payload['id'],
                'event_type_raw' => $eventTypeRaw,
                'event_type_canonical' => $canonicalType,
                'payload_json' => $request->json()->all(),
                'headers_json' => [
                    'x_billing_timestamp' => $request->header('X-Billing-Timestamp'),
                    'x_billing_signature' => $request->header('X-Billing-Signature'),
                ],
                'signature_verified_at' => now(),
                'processing_status' => 'pending',
                'attempt_count' => 1,
            ]);
        } catch (QueryException $exception) {
            $sqlState = (string) $exception->getCode();

            if (in_array($sqlState, ['23000', '23505'], true)) {
                return response()->json([
                    'message' => 'Duplicate event ignored.',
                ]);
            }

            throw $exception;
        }

        try {
            $processor->process($event);
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'message' => 'Webhook processing failed.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'data' => [
                'id' => $event->id,
                'external_event_id' => $event->external_event_id,
                'status' => $event->processing_status,
            ],
        ], Response::HTTP_CREATED);
    }
}

