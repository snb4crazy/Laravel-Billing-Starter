# Laravel Billing Starter Roadmap

This roadmap turns `docs/planning.md` ideas into phased, shippable deliverables.

## Progress Overview (as of May 2026)

### ✅ Documentation pack
- [x] `docs/planning.md` — original ideas and scope notes
- [x] `docs/roadmap.md` — this file, phased plan and milestones
- [x] `docs/security-model.md` — threat model, controls, production checklist
- [x] `docs/scaffold.md` — current state: dependencies, directory map, request flows
- [x] `docs/provider-contract.md` — adapter interface and canonical event/error models
- [x] `docs/webhook-spec.md` — ingest pipeline, idempotency, replay, SLOs
- [x] `docs/release-checklist.md` — quality and security gate before tagging
- [x] `docs/operations-runbook.md` — incident response, reconciliation, replay procedures
- [x] `docs/testing-strategy.md` — test pyramid, security test set, coverage areas
- [x] `docs/migration-guide.md` — incremental adoption guide for existing apps
- [x] `docs/api/openapi.yaml` — OpenAPI 3.0 contract (auth + billing endpoints)

### ✅ Phase 0 — Foundation

- [x] Base module namespaces (`App\Billing`, `App\Http\Controllers\Billing`, `App\Http\Controllers\Auth`)
- [x] Provider-agnostic DB schema migrations
  - [x] `plans` (soft-deletes, provider price IDs, features JSON)
  - [x] `subscriptions` (provider-agnostic, period timestamps)
  - [x] `payments` (amount in cents, refunded_at)
  - [x] `invoices` (hosted_url, pdf_url, dual amounts)
  - [x] `webhook_events` (unique provider+event_id, processing pipeline fields)
  - [x] `users` — `role` column added (`admin|customer`)
- [x] `config/billing.php` — provider secrets, idempotency TTL, webhook tolerance
- [x] Role baseline on `User` model (`isAdmin()`)
- [x] Eloquent models: `Plan`, `Subscription`, `Payment`, `Invoice`, `WebhookEvent`
- [x] Billing relationships on `User`

### ✅ Phase 1 — MVP Billing Core (partial)

- [x] `GET /api/billing/plans` — active plan listing
- [x] `POST /api/billing/subscriptions` — create subscription (stub provider)
- [x] `POST /api/billing/subscriptions/{id}/cancel` — cancel with ownership check
- [x] `POST /api/billing/checkout/session` — hosted checkout session (stub provider)
- [x] `GET /api/billing/payments` — payment history (scoped by role)
- [x] `GET /api/billing/invoices` — invoice history (scoped by role)
- [x] `POST /api/billing/webhooks/{provider}` — ingest, verify, deduplicate, persist
- [x] Webhook HMAC-SHA256 signature + timestamp replay protection (`VerifyWebhookSignature`)
- [x] Idempotency key enforcement on all mutating billing routes (`RequireIdempotencyKey`)
- [x] Admin role guard middleware (`EnsureBillingAdmin`)
- [x] Rate limiters: `api` (120/min), `auth` (10/min), `webhooks` (240/min)
- [x] Canonical event type mapping in `WebhookController`
- [x] Billing provider abstraction: `BillingProvider` contract + `NullBillingProvider` stub
- [x] `ProviderManager` resolver

### ✅ Authentication

- [x] `laravel/sanctum ^4.3` installed and wired
- [x] `HasApiTokens` on `User`
- [x] `POST /api/auth/register` — creates customer, returns bearer token
- [x] `POST /api/auth/login` — returns bearer token
- [x] `GET /api/auth/me` — returns authenticated user profile
- [x] `POST /api/auth/logout` — revokes current token
- [x] All billing routes protected with `auth:sanctum`
- [x] Password policy enforced at registration (min 12 chars, mixed case, numbers)

### ✅ Tests

- [x] Register + login + me + logout flows
- [x] Unauthenticated request rejection
- [x] Active plan listing with inactive plan excluded
- [x] Idempotency key duplicate rejection
- [x] Webhook signature verification + event deduplication
- [x] 12/12 passing, 26 assertions

---

## Next Features — Prioritised

### 🔜 Next: Policy-based Authorization (high priority, low effort)

Replace ad-hoc ownership checks in controllers with proper Laravel Policy classes.

- [ ] `SubscriptionPolicy` — view/cancel own subscription; admin can access all
- [ ] `PaymentPolicy` — view own payments
- [ ] `InvoicePolicy` — view own invoices
- [ ] Bind policies in `AuthServiceProvider`
- [ ] Update controllers to use `$this->authorize()`
- [ ] Add policy tests (cross-user 403, admin passthrough)

### 🔜 Next: Real Stripe Adapter (highest value, enables real testing)

Replace `NullBillingProvider` with a working Stripe implementation.

- [ ] `composer require stripe/stripe-php`
- [ ] `app/Billing/Providers/StripeProvider.php` — implements `BillingProvider`
  - [ ] `createCheckoutSession()` → Stripe Checkout Session API
  - [ ] `createSubscription()` → Stripe Subscription API
- [ ] Add `STRIPE_SECRET_KEY` to `.env.example` and `config/billing.php`
- [ ] Switch `ProviderManager` to resolve Stripe when provider is `stripe`
- [ ] Contract tests against Stripe test mode
- [ ] Update `docs/scaffold.md`

### 🔜 Next: Webhook Domain Handlers (completes Phase 1 acceptance criteria)

Currently webhooks are stored but no billing state is updated. Fix the "never trust frontend" loop.

- [ ] `app/Billing/Webhooks/Handlers/` directory
- [ ] `CheckoutCompletedHandler` — create `Payment` record, mark `succeeded`
- [ ] `InvoicePaidHandler` — update `Invoice` to `paid`, set `paid_at`
- [ ] `InvoicePaymentFailedHandler` — update `Subscription` to `past_due`
- [ ] `SubscriptionCanceledHandler` — update `Subscription` to `canceled`
- [ ] Dispatch handlers from `WebhookController` (sync first, async queue later)
- [ ] Idempotency guard in each handler
- [ ] Handler tests for each event type
- [ ] Handler tests for duplicate delivery (no-op)

### 🔜 Next: Queued Webhook Processing (Phase 2 reliability)

- [ ] Move domain handler dispatch to a queued Job (`ProcessWebhookEvent`)
- [ ] Retry policy: bounded exponential backoff, max attempts config
- [ ] Failed job → update `webhook_events.processing_status` to `failed`
- [ ] Dead-letter: mark events after max retries, alert hook
- [ ] Admin replay endpoint: `POST /api/admin/webhooks/{id}/replay`
- [ ] Tests for retry, dead-letter, and replay

### 🔜 Next: Subscription Reactivation + Upgrade/Downgrade

- [ ] `POST /api/billing/subscriptions/{id}/resume` endpoint
- [ ] `POST /api/billing/subscriptions/{id}/change-plan` endpoint
- [ ] Provider adapter methods: `resumeSubscription()`, `changeSubscription()`
- [ ] Proration handling config flag
- [ ] Sync subscription state from `subscription.updated` webhook
- [ ] Tests for full lifecycle (create → cancel → resume; create → upgrade)

### 🔜 Next: Admin Dashboard Endpoints

- [ ] `GET /api/admin/plans` — list all plans (admin only)
- [ ] `POST /api/admin/plans` — create plan
- [ ] `PUT /api/admin/plans/{id}` — update plan
- [ ] `GET /api/admin/subscriptions` — list all subscriptions with filters
- [ ] `GET /api/admin/webhook-events` — list events with status filter
- [ ] `GET /api/admin/webhook-events/{id}` — single event detail
- [ ] Gate all routes with `billing.admin` middleware
- [ ] Tests for admin-only access (403 for customers)

### 🔜 Phase 3: Coupons/Discounts Module

- [ ] `coupons` migration (code, type, amount/percent, max_uses, expires_at)
- [ ] `coupon_usages` pivot migration
- [ ] `Coupon` + `CouponUsage` models
- [ ] Apply coupon at checkout session creation
- [ ] Provider adapter: pass coupon to Stripe Checkout
- [ ] `GET /api/billing/coupons/validate/{code}` endpoint
- [ ] Tests for valid/expired/exhausted coupons

### 🔜 Phase 3: Multi-Provider Skeletons

- [ ] `PayPalProvider` stub + capability matrix
- [ ] `PaddleProvider` stub + capability matrix (tax-inclusive pricing note)
- [ ] Provider selection by `BILLING_DEFAULT_PROVIDER` env var in `ProviderManager`
- [ ] Contract test suite runs for whichever provider is enabled

---

**Recommended starting order:**
1. **Webhook domain handlers** — closes the "trust webhook, not frontend" loop (core security principle)
2. **Policy-based authorization** — small but makes the architecture clean before it grows
3. **Real Stripe adapter** — unlocks end-to-end testing with real money flows in test mode
4. **Admin endpoints** — operational visibility for support and debugging

## Goals

- Build a reusable billing starter that can be integrated in parts.
- Keep provider coupling low by using adapter interfaces.
- Default to secure patterns (hosted checkout + verified webhooks).
- Support API-first apps (Laravel API, Flutter/web clients).

## Non-Goals (for initial releases)

- Full marketplace and payout orchestration in MVP.
- In-house card processing or raw card storage.
- Supporting every payment provider on day one.

## Product Tiers

### Tier 1 - One-Time Payments

- Hosted checkout sessions.
- Payment confirmation via webhook only.
- Transaction history.
- Basic invoice records.

### Tier 2 - Subscriptions

- Monthly/yearly plans.
- Trial periods.
- Upgrade/downgrade and cancellation/reactivation.
- Failed payment state handling.

### Tier 3 - Advanced Billing

- Coupons/discounts.
- Proration rules.
- Refund workflows.
- Customer billing portal integration.

### Tier 4 - Marketplace (Optional Module)

- Multi-vendor payments.
- Revenue split and payouts.
- Provider-specific capabilities (ex: Stripe Connect).

## Architecture Deliverables

- `Billing Core` module: plans, subscriptions, payments, invoices, ledger events.
- `Provider Adapter` module: shared interface and provider-specific implementations.
- `Webhook Pipeline` module: verification, idempotency, processing, replay support.
- `Admin` module: operational visibility and support workflows.
- `Observability` module: structured logs, metrics, and audit trail events.

## Phase Plan

## Phase 0 - Foundation

### Deliverables

- Base module boundaries and namespaces.
- Initial database schema:
  - `plans`
  - `subscriptions`
  - `payments`
  - `invoices`
  - `webhook_events`
- Feature flags/config toggles for enabling modules by app needs.
- Role model baseline (`admin`, `customer`) with policy placeholders.

### Acceptance Criteria

- Migrations are provider-agnostic where practical.
- Local environment runs with billing modules disabled/enabled by config.
- Webhook event store exists with unique external event IDs.

## Phase 1 - MVP Billing Core

### Deliverables

- Plan listing and subscription create/cancel endpoints.
- One-time checkout session endpoint.
- Webhook ingest endpoint with signature verification.
- Idempotent event processor for key events:
  - `checkout.session.completed`
  - `invoice.paid`
  - `invoice.payment_failed`
  - `customer.subscription.deleted`
- API endpoints for invoice and payment history.

### Acceptance Criteria

- No payment status is trusted from frontend callbacks.
- Repeated webhook deliveries do not duplicate records.
- Users can view own billing history; admins can view all.

## Phase 2 - Reliability + Security Hardening

### Deliverables

- Refund and failed-payment recovery workflow.
- Retry queue and dead-letter strategy for webhook processing failures.
- Webhook replay support for support/ops.
- Immutable billing event log for audits.
- Alert hooks for persistent webhook failures.

### Acceptance Criteria

- Failed webhook processing is observable and recoverable.
- Sensitive data is masked in logs.
- Security checklist in `docs/security-model.md` is fully met.

## Phase 3 - Extensibility

### Deliverables

- Provider contract documentation and test suite.
- First provider adapter production-ready (Stripe).
- Additional provider skeletons (PayPal, Paddle, Square).
- Coupon/discount module.

### Acceptance Criteria

- New provider can be added without changing billing core logic.
- Contract tests pass for all enabled providers.

## Phase 4 - Optional Marketplace Module

### Deliverables

- Separate marketplace bounded context (vendors, payouts, split rules).
- Provider-specific payout integration.
- Vendor and platform ledger reconciliation jobs.

### Acceptance Criteria

- Marketplace module can be excluded without breaking core billing.
- Payout reconciliation reports exist and are test covered.

## Testing Deliverables (cross-phase)

- Unit tests for domain rules and state transitions.
- Integration tests for provider adapters and webhook handlers.
- API tests for authz boundaries and role behavior.
- End-to-end tests using provider test mode/webhook replay.
- Regression tests for idempotency and duplicate webhook delivery.

## Documentation Deliverables

Create and maintain these docs as part of release gates:

- `docs/security-model.md`
- `docs/provider-contract.md`
- `docs/webhook-spec.md`
- `docs/api/openapi.yaml`
- `docs/operations-runbook.md`
- `docs/testing-strategy.md`
- `docs/migration-guide.md`
- `docs/release-checklist.md`

## Suggested Milestones

- Milestone A: Phase 0 complete + schema and module skeleton.
- Milestone B: Phase 1 complete + MVP usable for one-time + subscriptions.
- Milestone C: Phase 2 complete + production hardening and runbooks.
- Milestone D: Phase 3 complete + second provider plugged in.
- Milestone E: Optional Phase 4 marketplace exploration.

## Release Gate (minimum for public template)

- Phase 1 acceptance criteria complete.
- High-severity security findings closed.
- Webhook idempotency tests passing.
- API contract and migration docs updated.
- Example integration flow validated in test mode.

