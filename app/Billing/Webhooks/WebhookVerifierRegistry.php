<?php

namespace App\Billing\Webhooks;

use App\Billing\Contracts\PayPalClientInterface;
use App\Billing\Webhooks\Verifiers\HmacWebhookVerifier;
use App\Billing\Webhooks\Verifiers\PayPalWebhookVerifier;
use App\Billing\Webhooks\Verifiers\StripeWebhookVerifier;
use App\Billing\Webhooks\Verifiers\WebhookVerifier;
use App\Billing\Contracts\PaddleClientInterface;
use App\Billing\Webhooks\Verifiers\PaddleWebhookVerifier;

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

    public function __construct(int $toleranceSeconds = 300, ?PayPalClientInterface $payPal = null, ?PaddleClientInterface $paddle = null)
    {
        $this->verifiers = [
            'stripe' => new StripeWebhookVerifier($toleranceSeconds),
        ];

        if ($payPal !== null) {
            $this->verifiers['paypal'] = new PayPalWebhookVerifier($payPal);
        }

        if ($paddle !== null) {
            $this->verifiers['paddle'] = new PaddleWebhookVerifier($paddle);
        }

        $this->default = new HmacWebhookVerifier($toleranceSeconds);
    }

    private HmacWebhookVerifier $default;

    public function for(string $provider): WebhookVerifier
    {
        return $this->verifiers[$provider] ?? $this->default;
    }
}

