# Laravel Billing Starter

Reusable, security-first billing backend template for Laravel API apps.

This project is intentionally modular so you can adopt it piece by piece in an existing app:

- auth first
- plans and subscriptions next
- checkout and webhooks next
- provider integrations (Stripe first) when ready

## What This Starter Includes

- Laravel 13 API baseline
- Sanctum bearer token authentication
- Billing domain models:
  - `plans`
  - `subscriptions`
  - `payments`
  - `invoices`
  - `webhook_events`
- Idempotency middleware for mutating billing routes
- Webhook signature verification middleware
- Role baseline (`admin`, `customer`)
- Policy-based authorization
- Stripe adapter layer with extractable boundaries

## Security Defaults

- Never trust frontend payment success callbacks.
- Treat provider webhooks as source of truth.
- Require idempotency keys on POST/PUT/PATCH/DELETE billing routes.
- Verify webhook signatures before processing.
- Maintain auditable `webhook_events` history.

## API Surface (Current)

Auth:

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/auth/me`
- `POST /api/auth/logout`

Billing:

- `GET /api/billing/plans`
- `POST /api/billing/checkout/session`
- `POST /api/billing/subscriptions`
- `POST /api/billing/subscriptions/{subscription}/cancel`
- `GET /api/billing/payments`
- `GET /api/billing/invoices`
- `POST /api/billing/webhooks/{provider}`

See `docs/api/openapi.yaml` for request/response schemas.

## Quick Start

1. Install dependencies
2. Configure environment
3. Run migrations
4. Start app

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Stripe Setup (Test Mode)

Set these env vars in `.env`:

```dotenv
BILLING_DEFAULT_PROVIDER=stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Forward webhooks locally:

```bash
stripe listen --forward-to http://localhost:8000/api/billing/webhooks/stripe
```

Detailed setup and extraction guide:

- `docs/stripe-integration-guide.md`

## Documentation Index

- `docs/planning.md`
- `docs/scaffold.md`
- `docs/roadmap.md`
- `docs/security-model.md`
- `docs/provider-contract.md`
- `docs/webhook-spec.md`
- `docs/testing-strategy.md`
- `docs/operations-runbook.md`
- `docs/migration-guide.md`
- `docs/release-checklist.md`
- `docs/api/openapi.yaml`
- `docs/stripe-integration-guide.md`

## Testing

```bash
php artisan test
```

## License

MIT
