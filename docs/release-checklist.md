# Release Checklist

Use this checklist before tagging any release of the billing starter.

## 1) Scope and Compatibility

- [ ] Release notes drafted with new features, fixes, and known limitations.
- [ ] Compatibility validated for targeted Laravel and PHP versions.
- [ ] Backward-incompatible changes documented.
- [ ] Migration guidance updated in `docs/migration-guide.md`.

## 2) Security Gate

- [ ] `docs/security-model.md` controls reviewed and still applicable.
- [ ] Webhook signature verification tests passing.
- [ ] Idempotency tests passing for duplicate webhook deliveries.
- [ ] Authorization tests passing for customer/admin boundaries.
- [ ] No secrets hardcoded in repository or sample files.
- [ ] Dependency vulnerability scan reviewed; high/critical issues addressed.

## 3) Billing Correctness

- [ ] Subscription lifecycle tests pass (create, change, cancel, reactivate).
- [ ] One-time payment flow validated in provider test mode.
- [ ] Invoice history and payment history endpoints validated.
- [ ] Refund workflow tested (where supported).
- [ ] Failed payment and retry paths validated.

## 4) Provider and Webhook Reliability

- [ ] Provider adapter contract tests passing.
- [ ] Canonical event mapping tests up to date.
- [ ] Webhook retry/dead-letter behavior validated.
- [ ] Replay workflow tested and audited.

## 5) Documentation Gate

- [ ] `docs/roadmap.md` reflects release status.
- [ ] `docs/provider-contract.md` updated for any contract changes.
- [ ] `docs/webhook-spec.md` updated for event changes.
- [ ] API contract updated in `docs/api/openapi.yaml`.
- [ ] Runbook and testing docs updated for operational changes.

## 6) Operational Readiness

- [ ] Logging fields verified (no sensitive leakage).
- [ ] Monitoring dashboards/alerts aligned with latest behavior.
- [ ] Incident runbook validated for webhook failure scenario.
- [ ] Rollback strategy confirmed.

## 7) Final Sign-Off

- [ ] Engineering sign-off.
- [ ] Security sign-off (or delegated review).
- [ ] Product sign-off on documented scope.
- [ ] Version tag and changelog finalized.

