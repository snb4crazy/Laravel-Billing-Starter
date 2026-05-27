<?php

namespace App\Billing\Webhooks;

use App\Billing\Webhooks\Verifiers\HmacWebhookVerifier;
use App\Billing\Webhooks\Verifiers\StripeWebhookVerifier;
use App\Billing\Webhooks\Verifiers\WebhookVerifier;

/**
 * Maps provider names to their webhook signature verifier implementations.
 *
 * Extraction note:
 *   Add an entry here when you add a new provider.
 *   Can be extended via config or dependency injection for full runtime flexibility.
 */
class WebhookVerifierRegistry
{
    /** @var array<string, WebhookVerifier> */
    private array $verifiers;

    public function __construct(int $toleranceSeconds = 300)
    {
        $this->verifiers = [
            'stripe' => new StripeWebhookVerifier($toleranceSeconds),
        ];

        $this->default = new HmacWebhookVerifier($toleranceSeconds);
    }

    private HmacWebhookVerifier $default;

    public function for(string $provider): WebhookVerifier
    {
        return $this->verifiers[$provider] ?? $this->default;
    }
}

