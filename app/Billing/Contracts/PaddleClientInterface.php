<?php

namespace App\Billing\Contracts;

/**
 * Thin, injectable wrapper for Paddle REST APIs.
 *
 * Keeping this as an interface makes Paddle integration extractable and testable:
 * - provider logic depends on this contract, not HTTP implementation details
 * - tests can mock this interface without external network calls
 */
interface PaddleClientInterface
{
    /**
     * Create a Paddle checkout for a product or subscription.
     *
     * @param  array<string, mixed>  $params
     * @return array{id:string,url:string,status:string}
     */
    public function createCheckout(array $params): array;

    /**
     * Create a Paddle subscription.
     *
     * @param  array<string, mixed>  $params
     * @return array{id:string,status:string}
     */
    public function createSubscription(array $params): array;

    /**
     * Verify a Paddle webhook signature.
     *
     * @param  array<string, mixed>  $params
     */
    public function verifyWebhookSignature(array $params): bool;
}

