# PayPal Webhook Event Mapping & Handlers

This document describes the PayPal webhook event mapping and domain-flow handlers with parity to Stripe webhook handling.

## Overview

PayPal webhooks are ingested, verified, normalized to canonical event types, and dispatched to domain handlers following the same architecture as Stripe webhooks.

## Event Mapping

PayPal webhook events are mapped to canonical event types in `WebhookController::EVENT_MAP`:

| PayPal Event Type | Canonical Type | Handler | Purpose |
|---|---|---|---|
| `PAYMENT.CAPTURE.COMPLETED` | `payment.succeeded` | `PaymentCompletedHandler` | One-time payment succeeded (order capture) |
| `PAYMENT.CAPTURE.DENIED` | `payment.failed` | `PaymentDeniedHandler` | One-time payment failed/denied |
| `BILLING.SUBSCRIPTION.ACTIVATED` | `subscription.activated` | `SubscriptionActivatedHandler` | Subscription activated and ready to bill |
| `BILLING.SUBSCRIPTION.CANCELLED` | `subscription.canceled` | `SubscriptionCanceledHandler` | Subscription canceled by user or system |

## Event Processing Pipeline

1. **Verification**: PayPal webhook signature verified using `PayPalWebhookVerifier` (calls PayPal's verify-webhook-signature endpoint)
2. **Persistence**: Raw event stored in `webhook_events` table
3. **Normalization**: Event type mapped from provider-specific to canonical type
4. **Deduplication**: Checked against `(provider, external_event_id)` unique constraint
5. **Processing**: Domain handler dispatched in database transaction
6. **Status Update**: Event marked as `processed` or `failed`

## Handler Specifications

### PaymentCompletedHandler (PAYMENT.CAPTURE.COMPLETED)

**Triggered when**: A PayPal order capture is successfully completed (money authorized and captured).

**Payload structure**:
```json
{
  "resource": {
    "id": "CAPTURE-123",
    "amount": {
      "value": "19.99",
      "currency_code": "USD"
    },
    "custom_id": "10",
    "supplementary_data": {
      "related_ids": {
        "order_id": "ORDER-123"
      }
    }
  }
}
```

**Domain action**:
- Creates or updates `Payment` record with status `succeeded`
- Amount converted from PayPal decimal format (e.g., "19.99") to cents (1999)
- Stores event ID and supplementary data in metadata

**Idempotency**: Using `updateOrCreate` on `(provider, provider_payment_id)` ensures duplicate deliveries are safe.

### PaymentDeniedHandler (PAYMENT.CAPTURE.DENIED)

**Triggered when**: A PayPal order capture is denied or fails (insufficient funds, card declined, etc.).

**Payload structure**:
```json
{
  "resource": {
    "id": "CAPTURE-456",
    "amount": {
      "value": "29.99",
      "currency_code": "USD"
    },
    "custom_id": "11",
    "status_details": {
      "reason": "INSUFFICIENT_FUNDS"
    }
  }
}
```

**Domain action**:
- Creates or updates `Payment` record with status `failed`
- Stores failure reason and status details in metadata

**Idempotency**: Using `updateOrCreate` ensures duplicate deliveries are safe.

### SubscriptionActivatedHandler (BILLING.SUBSCRIPTION.ACTIVATED)

**Triggered when**: A PayPal subscription is activated and ready to bill (after user approves or activation date arrives).

**Payload structure**:
```json
{
  "resource": {
    "id": "I-SUB-001",
    "status": "ACTIVE"
  }
}
```

**Domain action**:
- Updates `Subscription` record with status `active`
- Subscription becomes eligible for billing cycles

**Idempotency**: Subscription status is idempotent; setting to `active` multiple times is safe.

### SubscriptionCanceledHandler (BILLING.SUBSCRIPTION.CANCELLED)

**Triggered when**: A PayPal subscription is canceled by user, admin, or system (failed billing, etc.).

**Payload structure**:
```json
{
  "resource": {
    "id": "I-SUB-001",
    "status": "CANCELLED"
  }
}
```

**Domain action**:
- Updates `Subscription` record with status `canceled`
- Sets `canceled_at` timestamp

**Idempotency**: Subscription status is idempotent; setting to `canceled` multiple times is safe.

## User Resolution

Handlers resolve users from the webhook payload in this order:

1. `resource.supplementary_data.related_ids.order_id` (for one-time payments)
2. `resource.custom_id` (for subscriptions and explicit tracking)

If no valid user ID is found, the handler silently skips processing.

## PayPal Webhook Configuration

### Setup in PayPal Dashboard

1. Go to **Settings** → **Webhooks**
2. Create or select webhook endpoint:
   - URL: `https://your-app.com/api/billing/webhooks/paypal`
   - Enable event types:
     - `PAYMENT.CAPTURE.COMPLETED`
     - `PAYMENT.CAPTURE.DENIED`
     - `BILLING.SUBSCRIPTION.ACTIVATED`
     - `BILLING.SUBSCRIPTION.CANCELLED`
3. Copy webhook ID
4. Add to `.env`:
   ```dotenv
   PAYPAL_WEBHOOK_ID=<webhook-id>
   ```

### Required Headers

PayPal must send these headers (verified by `PayPalWebhookVerifier`):

- `PayPal-Transmission-Id`: Unique transmission ID
- `PayPal-Transmission-Time`: ISO 8601 transmission timestamp
- `PayPal-Transmission-Sig`: HMAC signature over transmission data
- `PayPal-Cert-Url`: URL to certificate used for signing
- `PayPal-Auth-Algo`: Algorithm used (SHA256withRSA)

## Testing Webhooks Locally

### Using PayPal Sandbox

1. Create sandbox account in PayPal Developer Dashboard
2. Set up webhook in sandbox (same as production)
3. Use webhook simulator in PayPal Dashboard to test events

### Manual Testing

Create a webhook event record and invoke the processor directly:

```php
$event = WebhookEvent::create([
    'provider' => 'paypal',
    'external_event_id' => 'WH-CAPTURE-001',
    'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
    'event_type_canonical' => 'payment.succeeded',
    'payload_json' => [
        'resource' => [
            'id' => 'CAPTURE-123',
            'amount' => ['value' => '19.99', 'currency_code' => 'USD'],
            'custom_id' => '1',
        ],
    ],
    'headers_json' => [],
    'signature_verified_at' => now(),
    'processing_status' => 'pending',
]);

app(WebhookEventProcessor::class)->process($event);
```

## Failure Handling

If a handler throws an exception:

1. Exception is caught in `WebhookController`
2. Event marked as `failed` with failure reason
3. HTTP 500 returned (allows PayPal to retry)
4. Logs contain full exception trace for debugging

To retry manually:

```php
$event->forceFill([
    'processing_status' => 'pending',
    'attempt_count' => $event->attempt_count + 1,
])->save();

app(WebhookEventProcessor::class)->process($event);
```

## Troubleshooting

### Webhooks not received

1. **Check webhook URL is accessible**: `curl https://your-app.com/api/billing/webhooks/paypal -X POST`
2. **Verify webhook is active in PayPal Dashboard**: Check status and recent delivery logs
3. **Check logs**: `storage/logs/laravel.log` for signature verification errors

### Payment succeeded but order not created

1. Check if `custom_id` matches a user ID: `select * from users where id = <custom_id>`
2. Check webhook payload: Are `resource.id` and user ID present?
3. Check handler logs: Review any skip conditions (missing user, bad data)

### Duplicate payment records created

1. Check `webhook_events` unique constraint: `(provider, external_event_id)`
2. Check if handler received duplicate webhook delivery
3. Handler uses `updateOrCreate`, so duplicates should be safe

## Schema Overview

**webhook_events table**:
```sql
CREATE TABLE webhook_events (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  provider VARCHAR(32) NOT NULL,
  external_event_id VARCHAR(255) NOT NULL,
  event_type_raw VARCHAR(255),
  event_type_canonical VARCHAR(255),
  payload_json JSON,
  headers_json JSON,
  signature_verified_at TIMESTAMP,
  processing_status VARCHAR(32) DEFAULT 'pending',
  processed_at TIMESTAMP,
  failure_reason TEXT,
  attempt_count INT DEFAULT 1,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(provider, external_event_id)
);
```

**payments table**:
```sql
CREATE TABLE payments (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  provider VARCHAR(32) NOT NULL,
  provider_payment_id VARCHAR(255) NOT NULL,
  status VARCHAR(32),
  amount INT,
  currency VARCHAR(3),
  paid_at TIMESTAMP,
  metadata JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(provider, provider_payment_id)
);
```

**subscriptions table**:
```sql
CREATE TABLE subscriptions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  plan_id BIGINT,
  provider VARCHAR(32) NOT NULL,
  provider_subscription_id VARCHAR(255) NOT NULL,
  status VARCHAR(32) DEFAULT 'incomplete',
  canceled_at TIMESTAMP,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE(provider_subscription_id)
);
```

## Parity with Stripe Webhooks

Both Stripe and PayPal webhook handling follow the same architecture:

| Aspect | Stripe | PayPal |
|---|---|---|
| **Verification** | HMAC-SHA256 via SDK | PayPal's verify endpoint |
| **Event Map** | Stripe → Canonical | PayPal → Canonical |
| **Handlers** | Per canonical type | Per canonical type |
| **Idempotency** | `updateOrCreate` | `updateOrCreate` |
| **Storage** | `webhook_events` | `webhook_events` |
| **Transactions** | Yes | Yes |
| **Replay Support** | Yes | Yes |

## Files Modified/Created

### Modified
- `app/Http/Controllers/Billing/WebhookController.php` - Added PayPal event mappings
- `app/Billing/Webhooks/WebhookEventProcessor.php` - Added new handler routes
- `tests/TestCase.php` - Added `RefreshDatabase` trait

### Created
- `app/Billing/Webhooks/Handlers/PaymentCompletedHandler.php`
- `app/Billing/Webhooks/Handlers/PaymentDeniedHandler.php`
- `app/Billing/Webhooks/Handlers/SubscriptionActivatedHandler.php`
- `tests/Unit/PayPalWebhookHandlersTest.php`

## See Also

- [PayPal Integration Guide](./paypal-integration-guide.md)
- [Webhook Specification](./webhook-spec.md)
- [Operations Runbook](./operations-runbook.md)

