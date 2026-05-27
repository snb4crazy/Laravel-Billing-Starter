<?php

namespace App\Billing\Contracts;

/**
 * Thin, injectable wrapper for PayPal REST APIs.
 *
 * Keeping this as an interface makes PayPal integration extractable and testable:
 * - provider logic depends on this contract, not HTTP implementation details
 * - tests can mock this interface without external network calls
 */
interface PayPalClientInterface
{
    /**
     * Create a PayPal checkout order for one-time payment.
     *
     * @param  array<string, mixed>  $params
     * @return array{id:string,approve_url:string,status:string}
     */
    public function createCheckoutOrder(array $params): array;

    /**
     * Create a PayPal subscription.
     *
     * @param  array<string, mixed>  $params
     * @return array{id:string,status:string,approve_url:string|null}
     */
    public function createSubscription(array $params): array;

    /**
     * Verify a PayPal webhook signature.
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyWebhookSignature(array $params): bool;
}

