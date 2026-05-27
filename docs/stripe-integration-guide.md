# Stripe Integration Guide

How to wire a real Stripe account into this billing starter — and how to extract these pieces into a separate existing app.

---

## Part 1 — How This Integration Is Structured

The Stripe integration is deliberately isolated behind two thin layers so you can drop it into any app with minimal change.

```
Your App
  └── ProviderManager (resolves which provider to use)
        └── StripeProvider (implements BillingProvider)
              └── StripeClientInterface (injectable, mockable)
                    ├── StripeHttpClient      ← real Stripe SDK calls
                    └── NullStripeClient      ← stub, used when key is blank
```

Files you copy to integrate Stripe into another app:

| File | What it does |
|---|---|
| `app/Billing/Contracts/BillingProvider.php` | Provider-agnostic interface |
| `app/Billing/Contracts/StripeClientInterface.php` | Stripe SDK wrapper contract |
| `app/Billing/Stripe/StripeHttpClient.php` | Real Stripe SDK calls |
| `app/Billing/Stripe/NullStripeClient.php` | Stub for local/test use |
| `app/Billing/Providers/StripeProvider.php` | Business logic (checkout, subscription) |
| `app/Billing/Webhooks/Verifiers/StripeWebhookVerifier.php` | Stripe signature verification |
| `app/Billing/Webhooks/Verifiers/WebhookVerifier.php` | Interface |

---

## Part 2 — Stripe Account Setup

### Step 1: Create a Stripe account

1. Go to <https://dashboard.stripe.com/register>.
2. Complete identity and business verification.

### Step 2: Get your test API keys

1. Dashboard → **Developers** → **API keys**.
2. Copy **Secret key** starting with `sk_test_...`.
3. Never commit this to version control.

### Step 3: Create your plans/products in Stripe

1. Dashboard → **Product catalog** → **Add product**.
2. Set a name (e.g., "Starter Plan") and recurring billing interval.
3. Note the **Price ID** for each price (e.g., `price_1N...`).
4. Seed your `plans` table with these IDs:

```sql
INSERT INTO plans (name, slug, monthly_price, yearly_price, currency, provider, provider_plan_monthly_id, provider_plan_yearly_id, is_active)
VALUES ('Starter', 'starter', 1900, 19000, 'USD', 'stripe', 'price_XXX_monthly', 'price_XXX_yearly', 1);
```

Or use your DatabaseSeeder:

```php
Plan::create([
    'name' => 'Starter',
    'slug' => 'starter',
    'monthly_price' => 1900,    // cents
    'yearly_price'  => 19000,
    'currency'      => 'USD',
    'provider'      => 'stripe',
    'provider_plan_monthly_id' => 'price_YOUR_MONTHLY_ID',
    'provider_plan_yearly_id'  => 'price_YOUR_YEARLY_ID',
    'is_active'     => true,
]);
```

### Step 4: Set your environment variables

Copy `.env.example` into your `.env` and fill in:

```dotenv
BILLING_DEFAULT_PROVIDER=stripe

STRIPE_SECRET_KEY=sk_test_XXXXXX
STRIPE_WEBHOOK_SECRET=whsec_XXXXXX    # filled in Step 5
```

### Step 5: Register your webhook endpoint in Stripe

1. Install the Stripe CLI: <https://stripe.com/docs/stripe-cli>.
2. **Local development** — forward events to your app:

```bash
stripe listen --forward-to http://localhost:8000/api/billing/webhooks/stripe
```

Stripe CLI prints the webhook signing secret — paste it into `STRIPE_WEBHOOK_SECRET`.

3. **Production** — Dashboard → **Developers** → **Webhooks** → **Add endpoint**:
   - URL: `https://yourdomain.com/api/billing/webhooks/stripe`
   - Events to listen for (minimum required):

```
checkout.session.completed
invoice.paid
invoice.payment_failed
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
```

4. After saving, click **Reveal signing secret** and put it in `STRIPE_WEBHOOK_SECRET`.

---

## Part 3 — How the Checkout Flow Works

### One-time payment

```
Flutter/web client                     Laravel API                       Stripe
     |                                     |                                |
     | POST /api/billing/checkout/session  |                                |
     | { amount, currency,                 |                                |
     |   success_url, cancel_url }         |                                |
     |------------------------------------>|                                |
     |                                     | StripeProvider                 |
     |                                     | .createCheckoutSession()       |
     |                                     |  findOrCreateCustomer(email)   |
     |                                     |  checkout.sessions.create()    |
     |                                     |------------------------------> |
     |                                     |<------------------------------ |
     |                                     | { session_id, checkout_url }   |
     | <-----------------------------------|                                |
     | { data: { session_id,               |                                |
     |           checkout_url } }          |                                |
     |                                     |                                |
     | redirect to checkout_url            |                                |
     |-------------------------------------------------->|                  |
     |                                     |            user pays           |
     |                                     |            Stripe fires event  |
     |                                     |<--- POST /webhooks/stripe -----|
     |                                     |     checkout.session.completed  |
     |                                     | verify Stripe-Signature        |
     |                                     | CheckoutCompletedHandler       |
     |                                     | create Payment(status=succeeded)
     |                                     |                                |
     | GET /api/billing/payments           |                                |
     |------------------------------------>| query payments for user        |
     | <-----------------------------------|                                |
```

### Subscription payment

Same flow but `POST /api/billing/subscriptions` first, or a checkout session with `mode: subscription`. Stripe will automatically fire:
- `customer.subscription.created` → subscription exists
- `invoice.paid` → subscription goes `active`
- `invoice.payment_failed` → subscription goes `past_due`

---

## Part 4 — Extracting Into an Existing App

Use this step-by-step when adding Stripe billing to an app that already has users and auth.

### Step A: Copy the dependency layer

```bash
# From this repository into your app
cp app/Billing/Contracts/BillingProvider.php          your-app/app/Billing/Contracts/
cp app/Billing/Contracts/StripeClientInterface.php    your-app/app/Billing/Contracts/
cp app/Billing/Stripe/StripeHttpClient.php            your-app/app/Billing/Stripe/
cp app/Billing/Stripe/NullStripeClient.php            your-app/app/Billing/Stripe/
cp app/Billing/Providers/StripeProvider.php           your-app/app/Billing/Providers/
cp app/Billing/Providers/NullBillingProvider.php      your-app/app/Billing/Providers/
cp app/Billing/ProviderManager.php                    your-app/app/Billing/
```

### Step B: Copy the webhook layer

```bash
cp app/Billing/Webhooks/Verifiers/WebhookVerifier.php         your-app/app/Billing/Webhooks/Verifiers/
cp app/Billing/Webhooks/Verifiers/StripeWebhookVerifier.php   your-app/app/Billing/Webhooks/Verifiers/
cp app/Billing/Webhooks/Verifiers/HmacWebhookVerifier.php     your-app/app/Billing/Webhooks/Verifiers/
cp app/Billing/Webhooks/WebhookVerifierRegistry.php           your-app/app/Billing/Webhooks/
cp app/Billing/Webhooks/WebhookEventProcessor.php             your-app/app/Billing/Webhooks/
cp -r app/Billing/Webhooks/Handlers/                          your-app/app/Billing/Webhooks/Handlers/
```

### Step C: Copy the middleware

```bash
cp app/Http/Middleware/VerifyWebhookSignature.php   your-app/app/Http/Middleware/
cp app/Http/Middleware/RequireIdempotencyKey.php    your-app/app/Http/Middleware/
cp app/Http/Middleware/EnsureBillingAdmin.php       your-app/app/Http/Middleware/
```

### Step D: Copy the migrations and models

```bash
cp database/migrations/2026_05_26_0000*  your-app/database/migrations/
cp app/Models/Plan.php         your-app/app/Models/
cp app/Models/Subscription.php your-app/app/Models/
cp app/Models/Payment.php      your-app/app/Models/
cp app/Models/Invoice.php      your-app/app/Models/
cp app/Models/WebhookEvent.php your-app/app/Models/
```

Add `HasApiTokens` and billing relations to your existing `User`:

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens; // add this

    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function payments(): HasMany      { return $this->hasMany(Payment::class); }
    public function invoices(): HasMany      { return $this->hasMany(Invoice::class); }
    public function isAdmin(): bool          { return $this->role === 'admin'; }
}
```

### Step E: Register bindings in AppServiceProvider

```php
use App\Billing\Contracts\StripeClientInterface;
use App\Billing\Stripe\NullStripeClient;
use App\Billing\Stripe\StripeHttpClient;

public function register(): void
{
    $this->app->bind(StripeClientInterface::class, function (): StripeClientInterface {
        $key = (string) config('billing.providers.stripe.secret_key', '');
        return $key !== '' ? new StripeHttpClient($key) : new NullStripeClient();
    });
}
```

### Step F: Register middleware aliases

In `bootstrap/app.php` (Laravel 11+) or `app/Http/Kernel.php` (Laravel 10):

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'billing.admin'     => EnsureBillingAdmin::class,
        'idempotency'       => RequireIdempotencyKey::class,
        'webhook.signature' => VerifyWebhookSignature::class,
    ]);
})
```

### Step G: Copy the config and env vars

```bash
cp config/billing.php your-app/config/billing.php
```

Add to `.env`:

```dotenv
BILLING_DEFAULT_PROVIDER=stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Step H: Add routes

Copy billing routes from `routes/api.php` into your API route file:

```php
Route::prefix('billing')->group(function (): void {

    Route::post('/webhooks/{provider}', [WebhookController::class, 'handle'])
        ->middleware(['throttle:webhooks', 'webhook.signature']);

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/plans', [BillingPlanController::class, 'index']);
        Route::post('/checkout/session', [CheckoutSessionController::class, 'store'])->middleware('idempotency');
        Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('idempotency');
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->middleware('idempotency');
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/invoices', [InvoiceController::class, 'index']);
    });

});
```

### Step I: Run migrations

```bash
php artisan migrate
```

### Step J: Verify the webhook locally

```bash
stripe listen --forward-to http://localhost:8000/api/billing/webhooks/stripe
```

Then trigger a test event:

```bash
stripe trigger checkout.session.completed
```

You should see the event appear in your `webhook_events` table and the `Payment` model created.

---

## Part 5 — Testing

### Test with Stripe test mode

All Stripe test card numbers: <https://stripe.com/docs/testing#cards>

| Card | Result |
|---|---|
| `4242 4242 4242 4242` | Payment succeeds |
| `4000 0000 0000 9995` | Payment fails (insufficient funds) |
| `4000 0025 0000 3155` | Requires 3D Secure authentication |

### Unit-test your StripeProvider without hitting the network

Use the `StripeClientInterface` mock pattern — see `tests/Unit/StripeProviderTest.php`:

```php
$client = Mockery::mock(StripeClientInterface::class);
$client->shouldReceive('findOrCreateCustomer')->andReturn(['id' => 'cus_test']);
$client->shouldReceive('createCheckoutSession')->andReturn([
    'id' => 'cs_test', 'url' => 'https://checkout.stripe.com/test',
]);

$result = (new StripeProvider($client))->createCheckoutSession($user, $plan, [
    'interval'    => 'monthly',
    'success_url' => 'https://example.com/success',
    'cancel_url'  => 'https://example.com/cancel',
]);
```

### Test webhook verification in PHPUnit

Build a valid Stripe-Signature header — same as the `makeStripeSignatureHeader()` helper in `tests/Feature/BillingApiTest.php`:

```php
private function makeStripeSignatureHeader(string $rawPayload, string $secret): string
{
    $timestamp = now()->timestamp;
    $sig = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);
    return "t={$timestamp},v1={$sig}";
}
```

---

## Part 6 — Going Live Checklist

- [ ] Replaced `sk_test_` key with `sk_live_` key in production environment.
- [ ] Created a live webhook endpoint in Stripe Dashboard and set `STRIPE_WEBHOOK_SECRET`.
- [ ] Rate limiting configured for `/api/billing/webhooks/stripe`.
- [ ] `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` stored in secret manager (not plain `.env` in production).
- [ ] Webhook test event successfully received and processed.
- [ ] `webhook_events` table has correct retention policy set.
- [ ] Failed-payment email notification enabled in Stripe Dashboard (Smart Retries).
- [ ] Stripe Radar rules reviewed for fraud management.
- [ ] Tested with test card in Stripe test mode (all three scenarios: success, fail, 3DS).
- [ ] `docs/release-checklist.md` billing section signed off.

---

## Part 7 — Stripe Dashboard Tips

| What | Where |
|---|---|
| Monitor failed payments | **Billing** → **Subscriptions** → filter by `Past due` |
| Replay a failed webhook | **Developers** → **Webhooks** → select endpoint → **Resend** |
| Inspect a specific event | **Developers** → **Events** → search by event ID |
| Check customer balance | **Customers** → select customer → **Subscriptions** |
| Test webhook locally | `stripe listen --forward-to localhost:8000/api/billing/webhooks/stripe` |
| Trigger specific events | `stripe trigger invoice.payment_failed` |

