<?php

namespace App\Billing;

use App\Billing\Contracts\BillingProvider;
use App\Billing\Providers\NullBillingProvider;

class ProviderManager
{
    public function provider(?string $provider = null): BillingProvider
    {
        // Stub provider keeps API flows testable before wiring a real gateway SDK.
        return new NullBillingProvider();
    }
}

