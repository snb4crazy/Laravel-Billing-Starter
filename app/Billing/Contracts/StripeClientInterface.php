<?php

namespace App\Billing\Contracts;

/**
 * Thin, injectable wrapper around the Stripe SDK.
 *
 * All Stripe API calls go through this interface so that:
 *  - StripeProvider only depends on this interface, not on the SDK classes.
 *  - Tests can inject a mock without hitting the network.
 *  - Swapping to a different HTTP client in the future requires only one change.
 */
interface StripeClientInterface
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>  Serialised Stripe\Customer object fields
     */
    public function findOrCreateCustomer(string $email, array $metadata = []): array;

    /**
     * @param  array<string, mixed>  $params
     * @return array{id:string,url:string}
     */
    public function createCheckoutSession(array $params): array;

    /**
     * @return array{id:string,status:string}
     */
    public function createSubscription(array $params): array;

    /**
     * Verify a Stripe webhook signature and return the decoded event payload.
     * Throws \Stripe\Exception\SignatureVerificationException on failure.
     *
     * @return array<string, mixed>
     */
    public function constructWebhookEvent(string $rawPayload, string $signatureHeader, string $secret): array;
}

