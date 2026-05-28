# Paddle Integration Guide

## What is Paddle?

Paddle is a **SaaS billing and payments platform** that specializes in **tax compliance, subscription management, and revenue optimization** for digital products and software companies. Unlike traditional payment processors (Stripe, PayPal), Paddle handles sales tax, VAT, and other regulatory requirements automatically.

### Key Characteristics

- **All-in-one solution**: payments + subscriptions + invoicing + tax compliance
- **Tax-inclusive pricing**: Paddle calculates and handles taxes automatically (VAT, GST, sales tax)
- **Global seller**: handles 200+ tax jurisdictions
- **Developer-friendly webhook API**: REST and async events
- **P2P payout system**: built-in seller payouts and revenue splits

---

## When to Use Paddle

### ✅ **Paddle is ideal for:**

| Use Case | Why Paddle |
|----------|-----------|
| **SaaS/Software** selling globally | Automatic VAT & tax handling in 200+ regions |
| **Digital products** (courses, templates, plugins) | Tax compliance built-in; no manual tax filings |
| **Subscription businesses** with multi-territory users | Multi-currency, geo-aware pricing |
| **Minimal compliance overhead** | Paddle is the merchant of record; handles tax authority filings |
| **Quick time-to-market** | No tax integration work; just wire webhooks |
| **Revenue-focused** optimization | Paddle optimizes conversion via local pricing & payment methods |
| **Creator/indie products** | All regulatory overhead handled; focus on product |

### ❌ **Paddle is not ideal for:**

| Use Case | Why Not Paddle |
|----------|-----------|
| **Physical goods** with inventory | Paddle is digital-first; doesn't track inventory |
| **Marketplace/multi-vendor** payments | Paddle's payout model is simpler; not designed for splits |
| **Custom payment methods** | Limited custom integrations vs Stripe |
| **Lowest processing fees** for large volume | Stripe/PayPal may offer better rates at scale |
| **Direct merchant control** of tax liability | Paddle takes responsibility; limited customization |

---

## How Paddle Works

### 1. **Pricing Model**

Paddle charges a **flat fee per transaction** rather than a percentage:
- **Digital products**: 5% + $0.50 per transaction
- **Subscriptions**: 5% + $0.99 per month (first month only)
- **All inclusive**: Includes payment processing, VAT/tax handling, invoicing, support

Compare to Stripe (2.9% + $0.30 per transaction) or PayPal (2.2% + $0.30 per transaction).

### 2. **Tax Handling**

Paddle is the **merchant of record**, meaning:
- Paddle owns the sale and handles tax liability
- You receive revenue minus Paddle's fee
- No VAT/sales tax collected from your customers upfront — Paddle adds it to the total
- Paddle files taxes with authorities automatically (where applicable)

Example flow:
```
Product price: $99 (USD)
Customer in France: 99 + VAT (20%) = €121.80 charged
Paddle keeps: €121.80 - (5% + $0.50) fee
You receive: remainder (tax already handled by Paddle)
```

### 3. **Webhook Events**

Paddle sends async events for all billing changes:

| Event | Example | Use |
|-------|---------|-----|
| `transaction.completed` | Payment successful | Create Payment record |
| `transaction.payment_failed` | Payment declined | Track failed attempt |
| `subscription.created` | Subscriber added | Create Subscription record |
| `subscription.updated` | Plan changed | Track upgrade/downgrade |
| `subscription.canceled` | User canceled | Update Subscription status |

### 4. **Customer Portal**

Paddle provides a **hosted customer portal** where users can:
- Update payment method
- Change plan (upgrade/downgrade)
- View invoices
- Cancel subscription

No custom UI needed for these workflows.

---

## Paddle vs. Competitors

| Feature | Paddle | Stripe | PayPal |
|---------|--------|--------|--------|
| **Tax handling** | Automatic (MoR) | Manual config | Manual config |
| **Subscription support** | ✅ Native | ✅ Via Billing API | ✅ Via Billing Plans |
| **Invoicing** | ✅ Automated | ✅ Via Invoices API | ✅ Via Invoices API |
| **Global currency support** | ✅ 150+ currencies | ✅ 135+ currencies | ✅ 200+ countries |
| **Checkout hosted UI** | ✅ Yes | ✅ Yes (Checkout) | ✅ Limited |
| **Webhook reliability** | ✅~99.9% with retry | ✅~99.9% with retry | ✅ Standard retry |
| **Developer experience** | ✅ REST API + webhooks | ✅ Exceptional (SDKs) | ✅ Good |
| **Marketplace/splits** | Limited | ✅ Connect (advanced) | ✅ PayPal Commerce Platform |
| **Processing fees** | 5% + fixed | 2.9% + $0.30 | 2.2% + $0.30 |
| **Tax filing** | ✅ Automatic | ❌ Manual | ❌ Manual |

**When Paddle wins**: Tax compliance, ease of use for non-US sellers, subscription focus  
**When Stripe wins**: Control, customization, enterprise support  
**When PayPal wins**: Consumer familiarity, existing PayPal balance payments

---

## Setting Up Paddle in This App

### 1. Create a Paddle Account

1. Go to [https://paddle.com/sign-up](https://paddle.com/sign-up)
2. Sign up and create a **Paddle Seller Account**
3. Verify your business details (tax residency, payout info)
4. Get your **API key** from **Paddle Sandbox** (testing) and **Paddle Live** (production)

### 2. Environment Variables

Add to `.env.example` and your local `.env`:

```env
# Paddle Configuration
PADDLE_VENDOR_ID=your-vendor-id-here
PADDLE_API_KEY=your-api-key-here
PADDLE_API_BASE_URL=https://api.sandbox.paddle.com
PADDLE_WEBHOOK_SECRET=your-webhook-secret-here
```

### 3. Configure in Laravel

Update `config/billing.php`:

```php
'providers' => [
    'paddle' => [
        'vendor_id' => env('PADDLE_VENDOR_ID'),
        'api_key' => env('PADDLE_API_KEY'),
        'base_url' => env('PADDLE_API_BASE_URL', 'https://api.sandbox.paddle.com'),
    ],
],

'webhooks' => [
    'providers' => [
        'paddle' => [
            'signing_secret' => env('PADDLE_WEBHOOK_SECRET'),
        ],
    ],
],
```

### 4. Webhook Setup

1. Log into **Paddle Dashboard**
2. Go to **Developers** → **Webhooks**
3. Add a new webhook endpoint: `https://yourapp.com/api/billing/webhooks/paddle`
4. Subscribe to events:
   - `transaction.completed`
   - `subscription.created`
   - `subscription.updated`
   - `subscription.canceled`
5. Copy the **Webhook Secret** and set `PADDLE_WEBHOOK_SECRET`

### 5. Set Default Provider (Optional)

Update `.env`:

```env
BILLING_DEFAULT_PROVIDER=paddle
```

Or leave Stripe as default and explicitly pass `provider: paddle` when creating checkout sessions.

---

## API Reference

### Create Checkout Session

```php
$provider = $providerManager->for('paddle');

$checkout = $provider->createCheckoutSession([
    'plan_id' => $plan->provider_plan_id, // Paddle Plan ID
    'user_id' => $user->id,
    'success_url' => 'https://app.example.com/checkout/success',
    'cancel_url' => 'https://app.example.com/checkout/cancel',
]);

// Returns: { id, url, status }
```

### Create Subscription

```php
$subscription = $provider->createSubscription([
    'plan_id' => $plan->provider_plan_id,
    'customer_email' => $user->email,
    'custom_data' => ['user_id' => $user->id],
]);

// Returns: { provider_subscription_id, status }
```

### Webhook Events

Paddle sends JSON payloads; this app automatically:
1. Verifies the signature with `PADDLE_WEBHOOK_SECRET`
2. Maps Paddle event types to canonical types
3. Routes to domain handlers (payment success, subscription created, etc.)
4. Updates your database state

---

## Example: Paddle Webhook Payload

### `transaction.completed`

```json
{
  "data": {
    "id": "txn_abc123",
    "transaction_id": "txn_abc123",
    "customer_id": "ctm_12345",
    "billing_period": {
      "starts_at": "2026-05-27T00:00:00Z",
      "ends_at": "2026-06-27T00:00:00Z"
    },
    "subscription_id": "sub_67890",
    "address_id": "add_456",
    "receipt_number": "REC-12345",
    "currency_code": "USD",
    "status": "completed",
    "details": {
      "tax_rate": 0,
      "subtotal": 9999,
      "tax": 0,
      "total": 9999,
      "receipt_number": "REC-12345"
    },
    "payments": [
      {
        "amount": 9999,
        "currency_code": "USD",
        "status": "captured",
        "receipt_number": "REC-12345"
      }
    ],
    "custom_data": {
      "user_id": "1"
    }
  },
  "event_id": "evt_abc123",
  "event_type": "transaction.completed",
  "occurred_at": "2026-05-27T12:30:45.000Z"
}
```

Key fields:
- `data.id` - Transaction ID
- `data.customer_id` - Paddle Customer ID (link to user via custom_data)
- `data.details.total` - Amount in cents
- `data.currency_code` - Currency (USD, EUR, etc.)
- `data.subscription_id` - Subscription ID (if recurring)
- `custom_data.user_id` - Your user ID (passed at checkout)

### `subscription.created`

```json
{
  "data": {
    "id": "sub_67890",
    "status": "active",
    "customer_id": "ctm_12345",
    "address_id": "add_456",
    "items": [
      {
        "status": "active",
        "quantity": 1,
        "recurring": true,
        "created_at": "2026-05-27T00:00:00Z",
        "updated_at": "2026-05-27T00:00:00Z",
        "next_billed_at": "2026-06-27T00:00:00Z",
        "paused_at": null,
        "product_id": "pro_1",
        "price_id": "pri_1"
      }
    ],
    "custom_data": {
      "user_id": "1"
    },
    "created_at": "2026-05-27T12:00:00Z",
    "updated_at": "2026-05-27T12:00:00Z"
  },
  "event_id": "evt_sub_created_001",
  "event_type": "subscription.created",
  "occurred_at": "2026-05-27T12:00:00Z"
}
```

---

## Common Workflows

### 1. **One-Time Purchase**

1. User clicks "Buy"
2. Create checkout session → Paddle Checkout URL
3. User pays in Paddle UI
4. Paddle sends `transaction.completed` webhook
5. App creates `Payment` record with `status = succeeded`

### 2. **Subscription Signup**

1. User selects plan
2. Create checkout session with `plan_id`
3. User completes payment in Paddle UI
4. Paddle sends:
   - `subscription.created` → app creates `Subscription` record
   - `transaction.completed` → app creates `Payment` record for first invoice
5. User gets access

### 3. **Plan Upgrade/Downgrade**

1. User selects new plan
2. Call `createSubscription()` with new plan (Paddle handles proration)
3. Paddle sends `subscription.updated` webhook
4. App updates `Subscription.plan_id` and timestamps

### 4. **Customer Cancellation**

1. User cancels in Paddle Customer Portal **or** app calls provider
2. Paddle sends `subscription.canceled` webhook
3. App updates `Subscription.status = canceled` and `canceled_at`
4. Access revoked on next request

---

## Testing with Paddle Sandbox

Paddle provides **Test Mode**, which:
- Uses fake payment methods (no real charges)
- Allows you to trigger webhook events manually
- Returns test transaction/subscription IDs

### Test Card Numbers

- **Visa**: `4242 4242 4242 4242` (any future expiry, any CVC)
- **Discover**: `6011 1111 1111 1111`

### Trigger Webhooks Manually

In **Paddle Sandbox Dashboard**:
1. Go **Developers** → **Webhooks**
2. Click **Test Events**
3. Select event type (e.g., `transaction.completed`)
4. Click **Send Test Event**

This helps you test handlers without payment flow.

---

## Troubleshooting

### Webhook Signature Verification Failed

- **Cause**: `PADDLE_WEBHOOK_SECRET` mismatch
- **Fix**: Copy exact secret from Paddle Dashboard → Developers → Webhooks → Settings

### Payment status not updating

- **Cause**: Handler not wired or event type not mapped
- **Debug**: Check `webhook_events` table for `processing_status = ignored` and `failure_reason`

### Customer portal not working

- **Cause**: `customer_id` not stored in `Subscription` model
- **Fix**: Ensure webhook handlers extract and persist `data.customer_id`

---

## Migration from Other Providers

If you're migrating from Stripe/PayPal:

1. **Keep old provider running** during transition
2. **Set `BILLING_DEFAULT_PROVIDER=paddle`** for new signups
3. **Existing subscriptions** stay on old provider until renewal
4. **At renewal**, create new Paddle subscription and link to existing user

Example workflow:

```php
// Existing Stripe subscription renewing
if ($subscription->provider === 'stripe' && $subscription->shouldMigrate()) {
    $paddleSubscription = $providerManager
        ->for('paddle')
        ->createSubscription([
            'plan_id' => $plan->paddle_plan_id,
            'customer_email' => $user->email,
            'custom_data' => ['user_id' => $user->id],
        ]);
    
    $subscription->update([
        'provider' => 'paddle',
        'provider_subscription_id' => $paddleSubscription['provider_subscription_id'],
    ]);
}
```

---

## Resources

- **Paddle Docs**: https://developer.paddle.com
- **Webhook Reference**: https://developer.paddle.com/webhooks/overview
- **Pricing Guide**: https://paddle.com/pricing/
- **Merchant of Record (MoR) Explained**: https://paddle.com/blog/merchant-of-record/
- **Tax Compliance**: https://paddle.com/tax-compliance/

---

## Summary

**Use Paddle if you:**
- Sell software/digital products globally
- Want automatic tax/VAT handling
- Prefer simplicity over customization
- Target non-US customers (VAT burden is high)
- Want built-in customer portal and subscriptions

**Stick with Stripe/PayPal if you:**
- Need low fees at scale
- Want maximum customization
- Manage physical goods or marketplaces
- Are primarily US-based
- Need premium enterprise support

---

