<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    private const EVENT_MAP = [
        'checkout.session.completed' => 'checkout.completed',
        'invoice.paid' => 'invoice.paid',
        'invoice.payment_failed' => 'invoice.payment_failed',
        'customer.subscription.created' => 'subscription.created',
        'customer.subscription.updated' => 'subscription.updated',
        'customer.subscription.deleted' => 'subscription.canceled',
        'charge.succeeded' => 'payment.succeeded',
        'charge.failed' => 'payment.failed',
    ];

    public function handle(Request $request, string $provider): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
        ]);

        $canonicalType = self::EVENT_MAP[$payload['type']] ?? null;

        try {
            $event = WebhookEvent::query()->create([
                'provider' => $provider,
                'external_event_id' => $payload['id'],
                'event_type_raw' => $payload['type'],
                'event_type_canonical' => $canonicalType,
                'payload_json' => $request->json()->all(),
                'headers_json' => [
                    'x_billing_timestamp' => $request->header('X-Billing-Timestamp'),
                    'x_billing_signature' => $request->header('X-Billing-Signature'),
                ],
                'signature_verified_at' => now(),
                'processing_status' => 'processed',
                'attempt_count' => 1,
                'processed_at' => now(),
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

        return response()->json([
            'data' => [
                'id' => $event->id,
                'external_event_id' => $event->external_event_id,
                'status' => $event->processing_status,
            ],
        ], Response::HTTP_CREATED);
    }
}

