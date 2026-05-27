# Security Model - Laravel Billing Starter

This document defines the minimum security baseline for the billing starter.

## Security Objectives

- Protect payment-related data and secrets.
- Ensure billing state changes are authentic and tamper-resistant.
- Keep PCI exposure minimal by preferring hosted payment pages.
- Provide traceability for audits and incident response.

## Core Principles

- Never trust frontend payment success callbacks.
- Treat provider webhooks as the source of truth for billing state.
- Apply least privilege to users, services, and API keys.
- Fail safely (do not grant paid access on uncertain payment state).

## Threat Model (High-Level)

### Assets

- Subscription and payment state.
- API credentials and webhook signing secrets.
- Invoice metadata and customer identity data.
- Audit trail (`webhook_events`, billing lifecycle events).

### Primary Threats

- Forged or replayed webhooks.
- Broken access control on billing endpoints.
- Secret leakage via logs, code, or misconfigured environments.
- Duplicate processing and inconsistent billing state.
- Abuse via brute force, endpoint flooding, or retry storms.

## Security Controls

## 1) Authentication and Authorization

- Use token-based API auth (Sanctum) with token scoping where possible.
- Enforce authorization policies for every billing resource.
- Partition admin and customer abilities clearly.
- Require re-auth for sensitive profile/payment management actions.

## 2) Webhook Security

- Verify provider signature on every webhook request.
- Enforce timestamp tolerance to reduce replay risk.
- Store provider event IDs and reject duplicates (idempotency).
- Return non-2xx only for retry-worthy failures.
- Keep raw payload and verification result for forensics.

## 3) Data Protection

- Encrypt sensitive fields at rest using framework-supported encryption.
- Enforce TLS in transit for all external calls.
- Minimize stored PII and payment metadata.
- Never store raw card PAN/CVV in application databases.
- Mask sensitive tokens and IDs in logs and responses.

## 4) Secrets Management

- Store secrets in environment/secret manager, never in repository.
- Use separate credentials for local/test/staging/production.
- Rotate API keys and webhook secrets on a defined schedule.
- Immediately rotate secrets if exposure is suspected.

## 5) Input Validation and API Hardening

- Validate all request payloads with strict schemas.
- Apply rate limiting on public billing and webhook endpoints.
- Use safe defaults for CORS and disable unnecessary origins.
- Keep CSRF protection for browser-based authenticated actions.

## 6) State Integrity and Idempotency

- Use explicit payment/subscription state machines.
- Make webhook handlers idempotent and transaction-safe.
- Use DB constraints (unique external IDs, foreign keys, enum/status checks).
- Record state transitions with actor/event context.

## 7) Logging, Monitoring, and Alerting

- Emit structured security and billing logs.
- Track webhook verification failures and processing latency.
- Alert on repeated failures, signature mismatches, and unusual spikes.
- Keep audit logs immutable and retention-defined.

## 8) Operational Security

- Restrict production admin actions with least privilege.
- Use deployment approvals for billing-impacting changes.
- Maintain incident runbooks for payment and webhook failures.
- Test rollback and webhook replay procedures regularly.

## Compliance Notes

- Prefer hosted checkout/provider UI to reduce PCI DSS burden.
- If custom card entry is added later, reassess PCI scope immediately.
- Document data retention and deletion policies per region/regulation.

## Secure Development Requirements

- Peer review required for billing and security-sensitive changes.
- Run static analysis and dependency vulnerability scans in CI.
- Block release on high-severity security findings.
- Maintain dependency patch cadence for framework and SDKs.

## Test Requirements

- Authorization tests for each billing endpoint.
- Webhook signature validation tests (valid, invalid, expired timestamp).
- Idempotency tests for duplicate and out-of-order events.
- Failure-path tests for retries, dead-letter, and manual replay.
- Log redaction tests to prevent secret/PII leakage.

## Minimum Production Checklist

- [ ] Hosted checkout flow enabled (or PCI plan documented for custom flow).
- [ ] Webhook signature verification enabled and tested.
- [ ] Idempotency by provider event ID implemented.
- [ ] Role-based authorization policies enforced.
- [ ] Rate limiting configured for billing and webhook routes.
- [ ] Secrets separated by environment and rotation process documented.
- [ ] Structured logs and alerts for webhook failures in place.
- [ ] Security tests included in CI and passing.
- [ ] Incident runbook available for payment failures and webhook replay.

## Incident Response (Billing-Specific)

1. Triage alert and classify impact (availability, integrity, data exposure).
2. Freeze risky automated actions if state integrity is uncertain.
3. Verify webhook/provider status and replay missing events safely.
4. Reconcile subscription/payment states against provider source.
5. Rotate affected secrets and patch root cause.
6. Publish post-incident report with preventive actions.

## Ownership

- Product owner: defines supported payment capabilities and risk appetite.
- Engineering owner: maintains controls, tests, and incident runbooks.
- Security owner (or delegate): reviews threat model and release gate.

