# Migration Guide

How to adopt the billing starter incrementally in an existing Laravel app.

## Migration Philosophy

- Integrate in slices, not all at once.
- Start with passive data capture and webhook logging.
- Enable entitlements only after webhook reliability is proven.

## Prerequisites

- Existing auth setup (Sanctum recommended for API apps).
- Queue worker available for webhook/background jobs.
- Provider sandbox account and webhook endpoint access.

## Step 1 - Schema Introduction

- Add core billing tables:
  - `plans`
  - `subscriptions`
  - `payments`
  - `invoices`
  - `webhook_events`
- Keep nullable provider-specific fields initially.
- Add unique index for (`provider`, `external_event_id`).

## Step 2 - Read-Only Integration

- Add provider adapter and webhook endpoint.
- Verify signatures and persist webhook events.
- Do not yet mutate application entitlements.
- Validate event volume, mapping quality, and retry behavior.

## Step 3 - Controlled Write Enablement

- Turn on state updates for one billing flow (ex: one-time payments).
- Enable idempotent handler logic and monitoring alerts.
- Compare internal state with provider dashboard daily.

## Step 4 - Subscription Lifecycle Enablement

- Enable subscription create/cancel/renew handling.
- Add access control logic driven by canonical subscription state.
- Roll out by cohort or feature flag.

## Step 5 - Legacy Data Backfill (if needed)

- Import historical provider data into canonical tables.
- Mark backfilled records with provenance metadata.
- Run reconciliation reports before full cutover.

## Step 6 - Cutover and Hardening

- Switch frontend to hosted checkout flow.
- Remove direct trust in frontend payment success callbacks.
- Enforce full production checks in `docs/release-checklist.md`.

## Rollback Strategy

- Feature-flag all entitlement-impacting handlers.
- On critical issue, disable handlers while still ingesting events.
- Replay missed events after fix deployment.

## Data Mapping Notes

- Map provider subscription statuses to canonical status enum.
- Preserve raw provider IDs for traceability.
- Avoid provider-only assumptions in domain models.

## Common Pitfalls

- Processing webhook before signature verification.
- Missing dedupe constraints causing double side effects.
- Entitlement changes based on frontend callbacks.
- Logging raw sensitive webhook data in plaintext logs.

## Validation Checklist

- [ ] Signature verification working in current environment.
- [ ] Dedupe constraints and tests in place.
- [ ] Admin can inspect webhook processing history.
- [ ] Reconciliation report shows no unresolved critical drift.
- [ ] Access control updates are tied to verified billing state.

## Breaking Change Policy

When upgrading major versions of the starter:

- Review contract changes in `docs/provider-contract.md`.
- Apply migration scripts in order.
- Re-run regression pack from `docs/testing-strategy.md`.
- Perform staged rollout with monitoring.

