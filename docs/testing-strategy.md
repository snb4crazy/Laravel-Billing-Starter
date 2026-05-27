# Testing Strategy

Testing approach for a modular, provider-agnostic billing starter.

## Objectives

- Prove billing correctness and state integrity.
- Prevent regressions in webhook and subscription flows.
- Verify security-critical behavior continuously.

## Test Pyramid

## Unit Tests

Focus:

- Domain state transitions (subscription/payment/invoice).
- Proration and trial logic.
- Role and policy rules.
- Event normalization mapping.

## Integration Tests

Focus:

- Provider adapter interactions in sandbox/test mode.
- Webhook verification and ingestion pipeline.
- Persistence constraints and idempotency behavior.
- Queue/retry/dead-letter flows.

## API Tests

Focus:

- Endpoint contracts and validation errors.
- AuthN/AuthZ boundaries for admin/customer routes.
- Pagination/filtering for billing histories.

## End-to-End Tests

Focus:

- Hosted checkout -> webhook -> entitlement update.
- Subscription change lifecycle (upgrade/downgrade/cancel/reactivate).
- Failed payment and recovery journey.

## Security Test Set (Mandatory)

- Invalid/forged webhook signatures are rejected.
- Expired signature timestamp handling.
- Duplicate webhook delivery is no-op after first process.
- Sensitive log redaction checks.
- Access control denial tests for cross-tenant and role violations.

## Data and Environment Strategy

- Use isolated test databases per run.
- Seed deterministic plans/users for repeatable tests.
- Run provider-dependent suites against sandbox credentials only.
- Keep network-dependent tests tagged/separated from fast unit suite.

## CI Pipeline Recommendations

1. Static checks (lint, formatting, static analysis).
2. Unit tests.
3. Integration/API tests.
4. Security-focused tests.
5. Optional sandbox e2e suite (nightly or pre-release).

## Required Coverage Areas (not just percentage)

- Subscription state machine transitions.
- Webhook deduplication and out-of-order events.
- Refund and failed payment handling.
- Provider contract compatibility tests.

## Regression Pack (Release Blocking)

- Checkout completion to paid state.
- Invoice paid and failed event paths.
- Subscription cancellation and reactivation.
- Replay of previously failed webhook event.

## Failure Triage Guidance

- Tag failures by category (`domain`, `provider`, `webhook`, `authz`, `infra`).
- Prioritize correctness and security regressions as release blockers.
- Require reproducible minimal test for every production bug fix.

## Reporting

- Publish test summary per PR and release candidate.
- Track flaky tests and enforce ownership/remediation.
- Keep known gaps documented with risk and mitigation.

