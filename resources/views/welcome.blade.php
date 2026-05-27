<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel Billing Starter') }}</title>
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            margin: 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
        }

        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }

        p {
            margin: 0 0 14px;
            line-height: 1.6;
            color: #cbd5e1;
        }

        code {
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 6px;
            padding: 2px 6px;
            color: #93c5fd;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-top: 20px;
        }

        .panel {
            background: #0b1220;
            border: 1px solid #1f2937;
            border-radius: 10px;
            padding: 14px;
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 15px;
            color: #93c5fd;
        }

        .panel ul {
            margin: 0;
            padding-left: 18px;
        }

        .panel li {
            margin: 6px 0;
            color: #d1d5db;
        }

        .links {
            margin-top: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid #334155;
            padding: 9px 13px;
            color: #e2e8f0;
            background: #1e293b;
        }

        .btn.primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #f8fafc;
        }

        .footer {
            margin-top: 20px;
            font-size: 13px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Laravel Billing Starter</h1>
        <p>
            Modular, security-first billing backend template for API-first products.
            This scaffold is designed to be extracted into existing apps incrementally.
        </p>
        <p>
            Core rule: <strong>never trust frontend payment success</strong>. Persist billing state from
            verified webhook events only.
        </p>

        <div class="grid">
            <section class="panel">
                <h2>Current Modules</h2>
                <ul>
                    <li>Sanctum bearer auth (`/api/auth/*`)</li>
                    <li>Plans, subscriptions, payments, invoices</li>
                    <li>Webhook ingest + idempotency + audit logs</li>
                    <li>Stripe adapter abstraction layer</li>
                </ul>
            </section>

            <section class="panel">
                <h2>Key Endpoints</h2>
                <ul>
                    <li><code>GET /api/billing/plans</code></li>
                    <li><code>POST /api/billing/checkout/session</code></li>
                    <li><code>POST /api/billing/subscriptions</code></li>
                    <li><code>POST /api/billing/webhooks/{provider}</code></li>
                </ul>
            </section>

            <section class="panel">
                <h2>Docs</h2>
                <ul>
                    <li><code>docs/scaffold.md</code></li>
                    <li><code>docs/roadmap.md</code></li>
                    <li><code>docs/security-model.md</code></li>
                    <li><code>docs/stripe-integration-guide.md</code></li>
                </ul>
            </section>
        </div>

        <div class="links">
            <a class="btn primary" href="/up">Health Check</a>
            <a class="btn" href="https://github.com/stripe/stripe-php" target="_blank" rel="noreferrer">Stripe SDK</a>
            <a class="btn" href="https://laravel.com/docs/13.x/sanctum" target="_blank" rel="noreferrer">Sanctum Docs</a>
        </div>

        <div class="footer">
            Laravel v{{ app()->version() }} • Billing scaffold ready for incremental integration
        </div>
    </div>
</div>
</body>
</html>
