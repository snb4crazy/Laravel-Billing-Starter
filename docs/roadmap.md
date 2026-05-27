# Laravel Billing Starter Roadmap

This roadmap turns `docs/planning.md` ideas into phased, shippable deliverables.

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

