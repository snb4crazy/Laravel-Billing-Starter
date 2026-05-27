# Scaffold Reference

Current state of the Laravel Billing Starter — dependencies, directory map, module responsibilities, and HTTP request flows.

## Runtime Requirements

- PHP `^8.3`
- Laravel `^13.8`
- SQLite (default local), MySQL/Postgres supported via `DB_CONNECTION`
- Queue worker required for webhook retry/dead-letter (future phases)

## Composer Dependencies

### Production

| Package | Version | Purpose |
|---|---|---|
| `laravel/framework` | `^13.8` | Core framework |
| `laravel/sanctum` | `^4.x` | Bearer token authentication for API |
| `laravel/tinker` | `^3.0` | REPL for local debugging |

### Development

| Package | Version | Purpose |
|---|---|---|
| `fakerphp/faker` | `^1.23` | Factory data generation |
| `laravel/pail` | `^1.2.5` | Live log tailing |
| `laravel/pint` | `^1.27` | PHP code style fixer |
| `mockery/mockery` | `^1.6` | Test mocking |
| `nunomaduro/collision` | `^8.6` | Better test error output |
| `phpunit/phpunit` | `^12.5.12` | Test runner |

## Directory Map

```
app/
├── Billing/
│   ├── Contracts/
│   │   └── BillingProvider.php         # Provider adapter interface
│   ├── Providers/
│   │   └── NullBillingProvider.php     # Stub adapter (local/test)
│   └── ProviderManager.php             # Resolves provider adapter
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php      # register, login, logout, me
│   │   └── Billing/
│   │       ├── BillingPlanController.php
│   │       ├── CheckoutSessionController.php
│   │       ├── InvoiceController.php
│   │       ├── PaymentController.php
│   │       ├── SubscriptionController.php
│   │       └── WebhookController.php
│   ├── Middleware/
│   │   ├── EnsureBillingAdmin.php      # Role guard: admin only
│   │   ├── RequireIdempotencyKey.php   # Idempotency-Key header enforcement
│   │   └── VerifyWebhookSignature.php  # HMAC-SHA256 webhook signature check
│   └── Requests/
│       └── Billing/
│           ├── CreateCheckoutSessionRequest.php
│           └── StoreSubscriptionRequest.php
│
├── Models/
│   ├── User.php             # + role, isAdmin(), billing relations
│   ├── Plan.php
│   ├── Subscription.php
│   ├── Payment.php
│   ├── Invoice.php
│   └── WebhookEvent.php
│
└── Providers/
    └── AppServiceProvider.php   # Rate limiters: api, webhooks

config/
└── billing.php               # Provider secrets, idempotency TTL, webhook tolerance

database/migrations/
├── ..._create_users_table.php
├── ..._add_role_to_users_table.php
├── ..._create_plans_table.php
├── ..._create_subscriptions_table.php
├── ..._create_payments_table.php
├── ..._create_invoices_table.php
└── ..._create_webhook_events_table.php

routes/
├── api.php       # All billing API routes (prefixed /api)
└── web.php       # Welcome page only

docs/
├── planning.md
├── roadmap.md
├── security-model.md
├── scaffold.md             # (this file)
├── provider-contract.md
├── webhook-spec.md
├── release-checklist.md
├── operations-runbook.md
├── testing-strategy.md
├── migration-guide.md
└── api/
    └── openapi.yaml
```

## Configuration

### `config/billing.php`

| Key | Env var | Default | Description |
|---|---|---|---|
| `default_provider` | `BILLING_DEFAULT_PROVIDER` | `stripe` | Active payment provider |
| `idempotency.ttl_seconds` | `BILLING_IDEMPOTENCY_TTL_SECONDS` | `600` | Cache window for duplicate request detection |
| `webhooks.tolerance_seconds` | `BILLING_WEBHOOK_TOLERANCE_SECONDS` | `300` | Max age of a valid webhook timestamp |
| `webhooks.providers.*.signing_secret` | `{PROVIDER}_WEBHOOK_SECRET` | — | HMAC signing secret per provider |

## Database Schema (Current)

```
users
  id, name, email, password, role (admin|customer), timestamps

plans
  id, name, slug, description, monthly_price, yearly_price,
  currency, provider, provider_plan_monthly_id, provider_plan_yearly_id,
  features_json, is_active, timestamps, deleted_at

subscriptions
  id, user_id(FK), plan_id(FK), provider, provider_subscription_id (UNIQUE),
  status, trial_ends_at, current_period_starts_at,
  current_period_ends_at, canceled_at, metadata, timestamps

payments
  id, user_id(FK), subscription_id(FK nullable), provider,
  provider_payment_id (UNIQUE), status, amount, currency,
  paid_at, refunded_at, metadata, timestamps

invoices
  id, user_id(FK), subscription_id(FK nullable), payment_id(FK nullable),
  provider, provider_invoice_id (UNIQUE nullable),
  invoice_number (UNIQUE nullable), status, amount_due, amount_paid,
  currency, hosted_url, pdf_url, issued_at, due_at, paid_at,
  metadata, timestamps

webhook_events
  id, provider, external_event_id, event_type_raw, event_type_canonical,
  payload_json, headers_json, signature_verified_at, processing_status,
  attempt_count, failure_reason, processed_at, timestamps
  UNIQUE (provider, external_event_id)
```

## HTTP API Endpoints

All billing API endpoints are prefixed `/api`.

### Authentication

| Method | Path | Guard | Middleware |
|---|---|---|---|
| `POST` | `/api/auth/register` | — | `throttle:auth` |
| `POST` | `/api/auth/login` | — | `throttle:auth` |
| `POST` | `/api/auth/logout` | `auth:sanctum` | |
| `GET` | `/api/auth/me` | `auth:sanctum` | |

### Billing Plans

| Method | Path | Guard | Notes |
|---|---|---|---|
| `GET` | `/api/billing/plans` | `auth:sanctum` | Returns active plans only |

### Subscriptions

| Method | Path | Guard | Middleware |
|---|---|---|---|
| `POST` | `/api/billing/subscriptions` | `auth:sanctum` | `idempotency` |
| `POST` | `/api/billing/subscriptions/{id}/cancel` | `auth:sanctum` | `idempotency` |

### Checkout

| Method | Path | Guard | Middleware |
|---|---|---|---|
| `POST` | `/api/billing/checkout/session` | `auth:sanctum` | `idempotency` |

### Payments + Invoices

| Method | Path | Guard | Notes |
|---|---|---|---|
| `GET` | `/api/billing/payments` | `auth:sanctum` | Scoped to own records (admin sees all) |
| `GET` | `/api/billing/invoices` | `auth:sanctum` | Scoped to own records (admin sees all) |

### Webhooks

| Method | Path | Guard | Middleware |
|---|---|---|---|
| `POST` | `/api/billing/webhooks/{provider}` | — | `throttle:webhooks`, `webhook.signature` |

## Request Flows

### 1) Authenticated API Request

```
Client
  ─── POST /api/auth/login { email, password }
  ─── ◄ 200 { token: "..." }

Client (bearer token in Authorization header)
  ─── GET /api/billing/plans
  ─── [throttle:api] ──► 429 if exceeded
  ─── [auth:sanctum] ──► 401 if no/invalid token
  ─── BillingPlanController@index
  ─── ◄ 200 { data: [...] }
```

### 2) Checkout + Subscription Create

```
Client
  ─── POST /api/billing/checkout/session
       Headers: Authorization: Bearer {token}
                Idempotency-Key: {uuid}
       Body:    { plan_id, success_url, cancel_url }
  ─── [throttle:api]
  ─── [auth:sanctum]
  ─── [idempotency] ──► 409 if duplicate key+payload in TTL window
  ─── CheckoutSessionController@store
      ─── validates request via CreateCheckoutSessionRequest
      ─── resolves Plan
      ─── ProviderManager::provider(plan->provider)
          ─── NullBillingProvider::createCheckoutSession (stub)
          ─── (Stripe adapter: creates Stripe Checkout Session)
  ─── ◄ 201 { data: { session_id, checkout_url } }

Client redirects to checkout_url (hosted page on provider)
```

### 3) Webhook Ingest + Idempotent Processing

```
Payment Provider (Stripe/PayPal/etc.)
  ─── POST /api/billing/webhooks/stripe
       Headers: X-Billing-Timestamp: {unix}
                X-Billing-Signature: {hmac-sha256}
       Body:    { id: "evt_...", type: "invoice.paid", ... }
  ─── [throttle:webhooks] ──► 429 if abused
  ─── [webhook.signature]
      ─── reads signing_secret from config/billing.php
      ─── checks timestamp within tolerance_seconds
      ─── HMAC-SHA256(timestamp.body, secret) == signature
      ─── ──► 401 on any failure
  ─── WebhookController@handle
      ─── validates id + type fields
      ─── maps raw type ──► canonical type (EVENT_MAP)
      ─── WebhookEvent::create(...)
          ─── UNIQUE(provider, external_event_id) catches duplicates
          ─── duplicate ──► 200 "Duplicate event ignored."
  ─── ◄ 201 { data: { id, external_event_id, status } }

(Future Phase 2: queued domain handler applies billing state changes)
```

## Middleware Stack Summary

| Alias | Class | Applied to |
|---|---|---|
| `auth:sanctum` | Sanctum token guard | All authenticated billing routes |
| `throttle:api` | `ThrottleRequests` 120 req/min | API middleware group |
| `throttle:auth` | `ThrottleRequests` 10 req/min | Auth routes |
| `throttle:webhooks` | `ThrottleRequests` 240 req/min | Webhook endpoint |
| `webhook.signature` | `VerifyWebhookSignature` | Webhook endpoint |
| `idempotency` | `RequireIdempotencyKey` | Mutating billing routes |
| `billing.admin` | `EnsureBillingAdmin` | Admin-only routes (future) |

## Billing Provider Abstraction

```
BillingProvider (interface)
    createCheckoutSession(user, plan, options) -> { session_id, checkout_url }
    createSubscription(user, plan, interval)  -> { provider_subscription_id, status }

NullBillingProvider (current)
    Returns stub data with generated ULIDs.
    Allows routes and flows to be fully tested without real credentials.

ProviderManager
    Resolves provider by name.
    Future: reads BILLING_DEFAULT_PROVIDER, swaps in Stripe/PayPal adapters.
```

## What Is Not Yet Implemented (Next Phases)

- Real Stripe (or other) provider adapter.
- Domain event dispatch from `WebhookController` (queued handlers).
- Subscription state updates from webhook events.
- Refund workflow.
- Admin dashboard endpoints.
- Coupon/discount module.
- Policy classes for fine-grained authorization (`SubscriptionPolicy`, etc.).

