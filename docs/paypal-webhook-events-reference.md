# PayPal Webhook Event Examples

Reference payloads for PayPal webhook events handled by the system.

## PAYMENT.CAPTURE.COMPLETED

Sent when a customer successfully completes a PayPal order capture (payment).

```json
{
  "id": "WH-2D986902ZA364554D-86H5DKQP857697933",
  "event_type": "PAYMENT.CAPTURE.COMPLETED",
  "create_time": "2026-05-27T10:15:30Z",
  "resource_type": "capture",
  "resource": {
    "status": "COMPLETED",
    "id": "3C679366CB519814H",
    "create_time": "2026-05-27T10:15:30Z",
    "update_time": "2026-05-27T10:15:30Z",
    "amount": {
      "currency_code": "USD",
      "value": "29.99"
    },
    "custom_id": "10",
    "payer": {
      "email_address": "payer@example.com",
      "account_id": "AQCWWLCYW_mRIZss4U_n7Z6w6dd33duPRRKips2-kxWy1T5SqNEqBvIIPAZZ"
    },
    "supplementary_data": {
      "related_ids": {
        "order_id": "7R14637451984050K"
      }
    },
    "links": [
      {
        "rel": "up",
        "href": "https://api-m.paypal.com/v2/checkout/orders/7R14637451984050K"
      }
    ]
  },
  "links": [
    {
      "rel": "resend",
      "href": "https://api-m.paypal.com/v2/notifications/webhooks-events/WH-2D986902ZA364554D-86H5DKQP857697933/resend"
    }
  ]
}
```

**Handler Action**: Creates Payment record with status = "succeeded"

---

## PAYMENT.CAPTURE.DENIED

Sent when a PayPal order capture fails (payment denied, insufficient funds, etc).

```json
{
  "id": "WH-7G986902ZA364554D-86H5DKQP857697934",
  "event_type": "PAYMENT.CAPTURE.DENIED",
  "create_time": "2026-05-27T10:16:30Z",
  "resource_type": "capture",
  "resource": {
    "status": "DENIED",
    "id": "4D689376CB519814J",
    "create_time": "2026-05-27T10:16:30Z",
    "update_time": "2026-05-27T10:16:30Z",
    "amount": {
      "currency_code": "USD",
      "value": "49.99"
    },
    "custom_id": "11",
    "payer": {
      "email_address": "payer@example.com",
      "account_id": "AQCWWLCYW_mRIZss4U_n7Z6w6dd33duPRRKips2-kxWy1T5SqNEqBvIIPAZZ"
    },
    "status_details": {
      "reason": "INSUFFICIENT_FUNDS"
    },
    "links": [
      {
        "rel": "up",
        "href": "https://api-m.paypal.com/v2/checkout/orders/8S14637451984050L"
      }
    ]
  },
  "links": [
    {
      "rel": "resend",
      "href": "https://api-m.paypal.com/v2/notifications/webhooks-events/WH-7G986902ZA364554D-86H5DKQP857697934/resend"
    }
  ]
}
```

**Handler Action**: Creates Payment record with status = "failed", captures failure reason

---

## BILLING.SUBSCRIPTION.ACTIVATED

Sent when a PayPal subscription is activated and ready to bill.

```json
{
  "id": "WH-8H986902ZA364554D-86H5DKQP857697935",
  "event_type": "BILLING.SUBSCRIPTION.ACTIVATED",
  "create_time": "2026-05-27T10:17:30Z",
  "resource_type": "subscription",
  "resource": {
    "id": "I-7AMYB5YK8SYA",
    "status": "ACTIVE",
    "status_update_time": "2026-05-27T10:17:30Z",
    "start_time": "2026-05-27T10:17:30Z",
    "plan_id": "P-YEARLY-001",
    "quantity": "1",
    "payer": {
      "email_address": "subscriber@example.com",
      "payer_id": "AQCWWLCYW_mRIZss4U_n7Z6w6dd33duPRRKips2-kxWy1T5SqNEqBvIIPAZZ",
      "name": {
        "given_name": "Bob",
        "surname": "Doe"
      }
    },
    "billing_info": {
      "outstanding_balance": {
        "currency_code": "USD",
        "value": "0.00"
      },
      "cycle_executions": [
        {
          "tenure_type": "REGULAR",
          "sequence": 1,
          "cycles_completed": 0,
          "cycles_remaining": 12,
          "total_cycles": 12
        }
      ],
      "last_payment": {
        "amount": {
          "currency_code": "USD",
          "value": "99.99"
        },
        "time": "2026-05-27T10:17:30Z"
      },
      "next_billing_time": "2027-05-27T10:17:30Z",
      "final_capture_time": "2026-07-27T10:17:30Z"
    },
    "links": [
      {
        "rel": "self",
        "href": "https://api-m.paypal.com/v2/billing/subscriptions/I-7AMYB5YK8SYA"
      }
    ]
  },
  "links": [
    {
      "rel": "resend",
      "href": "https://api-m.paypal.com/v2/notifications/webhooks-events/WH-8H986902ZA364554D-86H5DKQP857697935/resend"
    }
  ]
}
```

**Handler Action**: Updates Subscription with status = "active"

---

## BILLING.SUBSCRIPTION.CANCELLED

Sent when a PayPal subscription is cancelled by user, admin, or system.

```json
{
  "id": "WH-9I986902ZA364554D-86H5DKQP857697936",
  "event_type": "BILLING.SUBSCRIPTION.CANCELLED",
  "create_time": "2026-05-27T10:18:30Z",
  "resource_type": "subscription",
  "resource": {
    "id": "I-7AMYB5YK8SYA",
    "status": "CANCELLED",
    "status_update_time": "2026-05-27T10:18:30Z",
    "payer": {
      "email_address": "subscriber@example.com",
      "payer_id": "AQCWWLCYW_mRIZss4U_n7Z6w6dd33duPRRKips2-kxWy1T5SqNEqBvIIPAZZ",
      "name": {
        "given_name": "Bob",
        "surname": "Doe"
      }
    },
    "links": [
      {
        "rel": "self",
        "href": "https://api-m.paypal.com/v2/billing/subscriptions/I-7AMYB5YK8SYA"
      }
    ]
  },
  "links": [
    {
      "rel": "resend",
      "href": "https://api-m.paypal.com/v2/notifications/webhooks-events/WH-9I986902ZA364554D-86H5DKQP857697936/resend"
    }
  ]
}
```

**Handler Action**: Updates Subscription with status = "canceled", sets canceled_at

---

## Testing with PayPal Simulator

Use the PayPal Developer Dashboard webhook simulator to send test events:

1. Go to **Settings → Webhooks**
2. Select your webhook endpoint
3. Click **Send a test webhook**
4. Choose event type
5. Review and send

## Testing Locally

To manually test without PayPal:

```bash
# Create a test webhook event
php artisan tinker

$event = App\Models\WebhookEvent::create([
    'provider' => 'paypal',
    'external_event_id' => 'WH-TEST-001',
    'event_type_raw' => 'PAYMENT.CAPTURE.COMPLETED',
    'event_type_canonical' => 'payment.succeeded',
    'payload_json' => [
        'resource' => [
            'id' => 'CAPTURE-TEST-123',
            'amount' => ['value' => '19.99', 'currency_code' => 'USD'],
            'custom_id' => '1',
        ],
    ],
    'headers_json' => [],
    'signature_verified_at' => now(),
    'processing_status' => 'pending',
]);

app(App\Billing\Webhooks\WebhookEventProcessor::class)->process($event);

# Check results
$event->refresh();
echo $event->processing_status;  // should be 'processed'

# Check payment created
App\Models\Payment::where('provider_payment_id', 'CAPTURE-TEST-123')->first();
```

## Status Details Reasons

Common PayPal denial reasons in `status_details.reason`:

| Reason | Meaning |
|--------|---------|
| `INSUFFICIENT_FUNDS` | Customer account has insufficient funds |
| `INSTRUMENT_DECLINED` | Customer's payment instrument was declined |
| `PERMISSION_DENIED` | Account holder denied permission |
| `SYSTEM_ERROR` | PayPal system error |
| `DENIED_BY_CUSTOMER` | Customer explicitly denied payment |
| `INVALID_ACCOUNT` | Customer account is invalid/closed |
| `MANUALLY_DENIED` | Admin manually denied payment |

---

## Webhook Header Examples

PayPal sends these headers with webhook requests:

```
PayPal-Transmission-Id: dummytransmissionid123456789
PayPal-Transmission-Time: 2026-05-27T10:15:30Z
PayPal-Transmission-Sig: 3d9...signature...xyz
PayPal-Cert-Url: https://api.sandbox.paypal.com/v1/notifications/certs/CERT-KEY
PayPal-Auth-Algo: SHA256withRSA
```

These are verified by `PayPalWebhookVerifier` before processing.


