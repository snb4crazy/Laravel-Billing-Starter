# PayPal Integration Guide

This guide shows how to integrate PayPal in this starter and how to extract the same module into any existing Laravel app.

## Goals

- Keep PayPal integration modular and copy-paste friendly.
- Isolate provider logic behind contracts.
- Keep webhook verification provider-specific and secure.

## Architecture (Extractable Boundary)

The PayPal integration is intentionally split into small layers:

- `app/Billing/Contracts/PayPalClientInterface.php`
- `app/Billing/PayPal/PayPalHttpClient.php`
- `app/Billing/PayPal/NullPayPalClient.php`
- `app/Billing/Providers/PayPalProvider.php`
- `app/Billing/Webhooks/Verifiers/PayPalWebhookVerifier.php`

How it flows:

1. `ProviderManager` resolves provider (`paypal`).
2. `PayPalProvider` implements `BillingProvider` behavior.
3. `PayPalProvider` calls `PayPalClientInterface` methods.
4. `PayPalHttpClient` performs PayPal REST API calls.
5. Webhook middleware routes verification to `PayPalWebhookVerifier`.

## Step 1: Configure PayPal Credentials

In `.env`:

```dotenv
BILLING_DEFAULT_PROVIDER=paypal

PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_RETURN_URL=${APP_URL}/billing/paypal/return
PAYPAL_CANCEL_URL=${APP_URL}/billing/paypal/cancel

# PayPal webhook ID from PayPal dashboard
PAYPAL_WEBHOOK_ID=
```

Notes:

- Use sandbox credentials in development.
- `PAYPAL_WEBHOOK_ID` is required for signature verification.

## Step 2: Prepare Plan Mapping

For subscriptions, set PayPal plan IDs in your `plans` table:

- `provider = paypal`
- `provider_plan_monthly_id = P-...`
- `provider_plan_yearly_id = P-...`

For one-time checkout, `PayPalProvider::createCheckoutSession()` uses amount/currency values and does not require plan IDs.

## Step 3: Webhook Setup in PayPal

Configure webhook URL in PayPal dashboard:

- `https://your-app.com/api/billing/webhooks/paypal`

Ensure these headers are sent by PayPal (required by verifier):

- `PayPal-Transmission-Id`
- `PayPal-Transmission-Time`
- `PayPal-Transmission-Sig`
- `PayPal-Cert-Url`
- `PayPal-Auth-Algo`

`PayPalWebhookVerifier` validates signatures by calling PayPal's `verify-webhook-signature` endpoint using your API credentials.

## Step 4: Run and Validate

```bash
php artisan config:clear
php artisan test
```

Smoke test with API calls:

1. Create a plan with provider `paypal`.
2. `POST /api/billing/checkout/session` for one-time order.
3. `POST /api/billing/subscriptions` for recurring subscription.
4. Confirm `session_id` and `checkout_url` are returned.

## Extracting Into Another App

Copy these files into your target app:

- `app/Billing/Contracts/PayPalClientInterface.php`
- `app/Billing/PayPal/PayPalHttpClient.php`
- `app/Billing/PayPal/NullPayPalClient.php`
- `app/Billing/Providers/PayPalProvider.php`
- `app/Billing/Webhooks/Verifiers/PayPalWebhookVerifier.php`

Then wire these integration points:

1. Bind `PayPalClientInterface` in `AppServiceProvider::register()`.
2. Add `paypal` case in `ProviderManager`.
3. Add PayPal config keys in `config/billing.php`.
4. Add env vars in `.env.example`.
5. Ensure `VerifyWebhookSignature` uses `WebhookVerifierRegistry` with PayPal verifier.

## Why This Is Reusable

- Business flow depends on `BillingProvider`, not PayPal SDK directly.
- HTTP transport details are isolated in `PayPalHttpClient`.
- Tests can mock `PayPalClientInterface` with no network calls.
- Webhook verification logic is isolated from controller/business logic.

## Security Notes

- Never trust frontend checkout success directly.
- Update billing state only from verified webhook events.
- Keep API credentials and webhook IDs in secrets manager in production.
- Keep webhook tolerance and rate limiting enabled.

