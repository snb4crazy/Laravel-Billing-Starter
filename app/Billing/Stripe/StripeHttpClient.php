<?php

namespace App\Billing\Stripe;

use App\Billing\Contracts\StripeClientInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Concrete implementation of StripeClientInterface backed by the Stripe PHP SDK.
 *
 * Extraction note:
 *   Copy this class and StripeClientInterface into any other Laravel app.
 *   The only external dependency is `stripe/stripe-php`.
 */
class StripeHttpClient implements StripeClientInterface
{
    private StripeClient $stripe;

    public function __construct(string $apiKey)
    {
        $this->stripe = new StripeClient($apiKey);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function findOrCreateCustomer(string $email, array $metadata = []): array
    {
        $existing = $this->stripe->customers->search([
            'query' => 'email:"'.addslashes($email).'"',
            'limit' => 1,
        ]);

        if (count($existing->data) > 0) {
            return $existing->data[0]->toArray();
        }

        $customer = $this->stripe->customers->create(array_filter([
            'email' => $email,
            'metadata' => $metadata ?: null,
        ]));

        return $customer->toArray();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id:string,url:string}
     */
    public function createCheckoutSession(array $params): array
    {
        $session = $this->stripe->checkout->sessions->create($params);

        return [
            'id' => $session->id,
            'url' => $session->url ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{id:string,status:string}
     */
    public function createSubscription(array $params): array
    {
        $subscription = $this->stripe->subscriptions->create($params);

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SignatureVerificationException
     */
    public function constructWebhookEvent(string $rawPayload, string $signatureHeader, string $secret): array
    {
        $event = Webhook::constructEvent($rawPayload, $signatureHeader, $secret);

        return $event->toArray();
    }
}

