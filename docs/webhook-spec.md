# Webhook Specification

This document defines webhook ingestion, verification, processing, and replay behavior.

## Goals

- Use webhooks as source of truth for billing state.
- Process events safely (authenticity, idempotency, ordering tolerance).
- Keep full audit trail for debugging and compliance.

## Endpoint Contract

- Endpoint: `POST /api/billing/webhooks/{provider}`
- Content-Type: provider native webhook format.
- Response codes:
  - `200`/`204`: accepted and processed (or safely deduped)
  - `400`: invalid payload format
  - `401`/`403`: signature verification failed
  - `429`: temporary throttle
  - `500`: transient server error (provider may retry)

## Verification Requirements

- Verify provider signature on every request.
- Enforce timestamp tolerance to mitigate replay attacks.
- Reject unknown provider IDs or inactive webhook endpoints.
- Store verification result (pass/fail + reason) for audit.

## Persistence (`webhook_events`)

Minimum fields:

- `id`
- `provider`
- `external_event_id`
- `event_type_raw`
- `event_type_canonical`
- `payload_json`
- `headers_json` (filtered)
- `signature_verified_at`
- `processing_status` (`pending`, `processed`, `failed`, `ignored`)
- `processed_at`
- `failure_reason`
- `attempt_count`

Constraints:

- Unique index on (`provider`, `external_event_id`).
- Payload retention policy documented in ops runbook.

## Processing Pipeline

1. Receive request.
2. Verify signature + timestamp.
3. Persist raw event record.
4. Normalize to canonical event type.
5. Deduplicate by (`provider`, `external_event_id`).
6. Dispatch domain handler transactionally.
7. Mark event processed or failed.
8. Emit metrics/logs.

## Canonical Event Mapping (Minimum)

Provider-specific raw events map into internal events:

- `checkout.session.completed` -> `checkout.completed`
- `invoice.paid` -> `invoice.paid`
- `invoice.payment_failed` -> `invoice.payment_failed`
- `customer.subscription.created` -> `subscription.created`
- `customer.subscription.updated` -> `subscription.updated`
- `customer.subscription.deleted` -> `subscription.canceled`
- `charge.succeeded` / equivalent -> `payment.succeeded`
- `charge.failed` / equivalent -> `payment.failed`

## Domain Handling Rules

- Never grant paid entitlements from frontend callback alone.
- Apply state transitions with optimistic or row-level locking.
- Ignore unknown optional events unless explicitly supported.
- Handle out-of-order events defensively.

## Idempotency Rules

- Duplicate webhook deliveries must be no-op after first success.
- Handler side effects must be transaction-safe.
- Retries must not create duplicate financial records.

## Retry and Dead-Letter

- Failed processing increments `attempt_count` and schedules retry.
- Use bounded exponential backoff.
- Move permanently failing events to dead-letter queue/state.
- Provide admin action to replay selected events.

## Replay Support

Replay requirements:

- Replay by event ID and date range.
- Replay is audited (who, when, why).
- Replay respects idempotency and does not bypass verification policy.
- Replay in dry-run mode for diagnostics where possible.

## Security and Privacy

- Avoid logging full payloads with sensitive data in app logs.
- Retain raw payloads in controlled storage with access restrictions.
- Redact secrets and personal data from operational logs.

## Observability

Minimum metrics:

- Webhook request rate by provider.
- Signature verification failures.
- Processing success/failure count.
- Processing latency percentiles.
- Dead-letter queue size.

Minimum alerts:

- Verification failure spike.
- Processing failure threshold exceeded.
- Oldest pending event age exceeds SLO.

## Testing Requirements

- Signature verification tests (valid, invalid, expired timestamp).
- Duplicate delivery idempotency tests.
- Out-of-order event sequence tests.
- Retry and dead-letter behavior tests.
- Replay correctness tests.

## Operational SLO Targets (Initial)

- 99% of valid webhooks processed within 60 seconds.
- 100% of duplicate events deduped without double side effects.
- 0 critical auth bypass incidents.

