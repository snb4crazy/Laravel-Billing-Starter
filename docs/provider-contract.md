# Provider Contract

This document defines the adapter interface between `Billing Core` and payment providers.

## Purpose

- Keep billing domain logic provider-agnostic.
- Enable provider modules to be added or swapped with minimal core changes.
- Standardize error handling, idempotency, and event normalization.

## Design Rules

- `Billing Core` never calls provider SDKs directly.
- Every provider implementation maps external concepts to canonical domain models.
- Provider errors are translated to stable internal error types.
- Provider modules are feature-flagged and independently testable.

## Canonical Domain Models

### Plan

- `id`
- `provider`
- `provider_plan_id`
- `billing_interval` (`monthly`, `yearly`, ...)
- `amount`
- `currency`
- `active`

### Subscription

- `id`
- `user_id`
- `provider`
- `provider_subscription_id`
- `status` (`trialing`, `active`, `past_due`, `canceled`, `incomplete`)
- `trial_ends_at`
- `current_period_starts_at`
- `current_period_ends_at`
- `canceled_at`

### Payment

- `id`
- `user_id`
- `provider`
- `provider_payment_id`
- `status` (`pending`, `succeeded`, `failed`, `refunded`)
- `amount`
- `currency`
- `captured_at`

### Invoice

- `id`
- `user_id`
- `provider`
- `provider_invoice_id`
- `invoice_number`
- `status` (`draft`, `open`, `paid`, `void`, `uncollectible`)
- `amount_due`
- `amount_paid`
- `currency`
- `paid_at`
- `hosted_url`
- `pdf_url`

## Adapter Interface (Conceptual)

Provider adapters should expose these capabilities where supported.

### Customer and Checkout

- `createOrGetCustomer(user): ProviderCustomerRef`
- `createCheckoutSession(input): CheckoutSessionResult`
- `createBillingPortalSession(input): PortalSessionResult`

### Subscriptions

- `createSubscription(input): ProviderSubscriptionResult`
- `changeSubscription(input): ProviderSubscriptionResult`
- `cancelSubscription(input): ProviderSubscriptionResult`
- `resumeSubscription(input): ProviderSubscriptionResult`

### Payments and Refunds

- `createOneTimePayment(input): ProviderPaymentResult`
- `refundPayment(input): ProviderRefundResult`

### Invoices

- `getInvoice(input): ProviderInvoiceResult`
- `listInvoices(input): ProviderInvoiceCollectionResult`

### Webhooks

- `verifyWebhookSignature(payload, headers): VerificationResult`
- `normalizeWebhookEvent(payload, headers): NormalizedEvent`

## Normalized Webhook Event

All providers must return a common event envelope:

- `external_event_id`
- `provider`
- `occurred_at`
- `event_type` (canonical type)
- `entity_type` (`subscription`, `invoice`, `payment`, ...)
- `entity_external_id`
- `customer_external_id` (optional)
- `raw_payload`
- `raw_headers`

## Canonical Event Types

Minimum supported canonical events:

- `checkout.completed`
- `invoice.paid`
- `invoice.payment_failed`
- `subscription.created`
- `subscription.updated`
- `subscription.canceled`
- `payment.succeeded`
- `payment.failed`
- `payment.refunded`

## Error Contract

Provider-specific exceptions must map to internal error codes:

- `provider.auth_failed`
- `provider.rate_limited`
- `provider.invalid_request`
- `provider.not_found`
- `provider.conflict`
- `provider.unavailable`
- `provider.timeout`
- `provider.unknown`

Each mapped error should include:

- `code`
- `message`
- `retryable` (`true|false`)
- `provider_request_id` (if available)

## Idempotency Contract

- Outbound mutating operations must send an idempotency key when supported.
- Inbound webhook handling must dedupe by `external_event_id` + `provider`.
- Retries must not create duplicate subscriptions/payments/invoices.

## Capability Matrix

Each provider module must declare supported features:

- Hosted checkout
- Billing portal
- Subscription pause/resume
- Proration controls
- Coupons/discounts
- Refunds
- Webhook signature verification
- Invoice PDF links
- Marketplace/split payments

## Testing Requirements

- Contract tests against adapter interface for each provider.
- Webhook verification tests (valid/invalid/replay).
- Event normalization tests for mandatory event types.
- Idempotency tests for duplicate deliveries.
- Sandbox integration tests for major billing journeys.

## Versioning and Compatibility

- Contract is versioned (`v1`, `v2`, ...).
- Breaking changes require migration notes in `docs/migration-guide.md`.
- New optional methods should be additive and capability-guarded.

