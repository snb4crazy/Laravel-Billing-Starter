# Operations Runbook

Operational procedures for the Laravel Billing Starter.

## Purpose

- Provide standard response paths for billing incidents.
- Reduce recovery time for webhook and payment-state issues.
- Ensure consistent and auditable operational actions.

## Roles

- On-call engineer: triage and mitigation.
- Billing owner: domain correctness decisions.
- Security owner/delegate: incident severity and containment guidance.

## Key Dashboards and Signals

- Webhook ingest rate and error rate by provider.
- Signature verification failures.
- Pending and dead-letter webhook counts.
- Payment failures and refund failures.
- Subscription churn anomaly spikes.

## Common Incidents

## 1) Webhook Signature Failures Spike

### Symptoms

- Increased `401/403` on webhook endpoint.
- Events not updating subscription/payment state.

### Actions

1. Confirm provider webhook secret in current environment.
2. Verify endpoint URL and signing config in provider dashboard.
3. Check for timestamp drift on servers.
4. Rotate webhook secret if compromise or mismatch is suspected.
5. Replay missed events after verification is restored.

## 2) Duplicate Event Storm

### Symptoms

- Same external event appears many times.
- Risk of repeated side effects.

### Actions

1. Verify dedupe index (`provider`, `external_event_id`) health.
2. Confirm handlers are idempotent.
3. Temporarily throttle ingest if queue saturation occurs.
4. Reconcile affected records and backfill if required.

## 3) Persistent Processing Failures

### Symptoms

- Rising dead-letter count.
- Growing oldest pending event age.

### Actions

1. Inspect failure reasons and classify by root cause.
2. Apply fix and run targeted replay for failed events.
3. Monitor retry success and latency recovery.
4. Document incident and preventive action.

## 4) Payment State Mismatch With Provider

### Symptoms

- Internal status differs from provider dashboard.

### Actions

1. Pull provider source-of-truth event history.
2. Compare normalized events and local state timeline.
3. Replay missing events in chronological order when possible.
4. Run reconciliation script/report and capture audit record.

## Reconciliation Procedure (Daily/On-Demand)

1. Compare local paid invoices vs provider paid invoices.
2. Compare active subscriptions and period boundaries.
3. Detect orphan payments/invoices.
4. Generate discrepancy report.
5. Apply corrective replay/manual repair with approval.

## Replay Procedure

1. Select scope (single event ID or date range).
2. Run dry-run replay if available.
3. Execute replay with idempotency safeguards.
4. Record actor, reason, scope, and result in audit log.

## Security Incident Addendum

If secret exposure is suspected:

1. Rotate provider API keys and webhook signing secrets.
2. Invalidate affected app tokens if needed.
3. Increase monitoring sensitivity for anomalous activity.
4. Review logs for unauthorized actions.
5. Produce post-incident report.

## Change Management

- Billing-impacting changes require peer review.
- Schema changes must include rollback plan.
- Release must pass `docs/release-checklist.md`.

## Runbook Maintenance

- Review quarterly or after major incidents.
- Update thresholds, alerts, and contact rotations.
- Link latest scripts/tools used for reconciliation and replay.

