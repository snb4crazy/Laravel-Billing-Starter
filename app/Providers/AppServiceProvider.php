<?php

namespace App\Providers;

use App\Billing\Contracts\StripeClientInterface;
use App\Billing\Webhooks\WebhookVerifierRegistry;
use App\Billing\Stripe\NullStripeClient;
use App\Billing\Stripe\StripeHttpClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind StripeClientInterface so StripeProvider can be resolved via the container.
        // Guard against missing key to avoid crashing during artisan commands in test env.
        $this->app->bind(StripeClientInterface::class, function (): StripeClientInterface {
            $apiKey = (string) config('billing.providers.stripe.secret_key', '');

            if ($apiKey === '') {
                return new NullStripeClient();
            }

            return new StripeHttpClient($apiKey);
        });

        $this->app->singleton(WebhookVerifierRegistry::class, function (): WebhookVerifierRegistry {
            $toleranceSeconds = max((int) config('billing.webhooks.tolerance_seconds', 300), 60);

            return new WebhookVerifierRegistry($toleranceSeconds);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request): Limit {
            return Limit::perMinute(240)->by((string) $request->ip());
        });
    }
}
