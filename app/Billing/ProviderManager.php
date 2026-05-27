<?php

namespace App\Billing;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Contracts\PayPalClientInterface;
use App\Billing\Contracts\StripeClientInterface;
use App\Billing\Providers\NullBillingProvider;
use App\Billing\Providers\PayPalProvider;
use App\Billing\Providers\StripeProvider;
use Illuminate\Contracts\Container\Container;
use App\Billing\Contracts\PaddleClientInterface;
use App\Billing\Providers\PaddleProvider;

/**
 * Resolves the active BillingProvider for a given provider name.
 *
 * Extraction note:
 *   Add a case to resolve() when you add a new provider adapter.
 */
class ProviderManager
{
    public function __construct(private readonly Container $container)
    {
    }

    public function provider(?string $provider = null): BillingProvider
    {
        $name = $provider ?? config('billing.default_provider', 'stripe');

        return match ($name) {
            'stripe' => new StripeProvider($this->container->make(StripeClientInterface::class)),
            'paypal' => new PayPalProvider($this->container->make(PayPalClientInterface::class)),
            'paddle' => new PaddleProvider($this->container->make(PaddleClientInterface::class)),
            default => new NullBillingProvider(),
        };
    }
}

