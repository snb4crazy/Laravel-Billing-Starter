<?php

namespace App\Billing\Paddle;

use App\Billing\Contracts\PaddleClientInterface;
use Illuminate\Support\Facades\Http;

class PaddleHttpClient implements PaddleClientInterface
{
    public function __construct(
        private readonly string $vendorId,
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    public function createCheckout(array $params): array
    {
        $payload = [
            'items' => [
                [
                    'quantity' => 1,
                    'price_id' => $params['price_id'] ?? $params['product_id'],
                ],
            ],
            'custom_data' => $params['custom_data'] ?? [],
            'return_url' => $params['success_url'] ?? config('app.url'),
            'customer_email' => $params['customer_email'] ?? null,
        ];

        $response = Http::acceptJson()
            ->withHeader('Authorization', 'Bearer '.$this->apiKey)
            ->post($this->baseUrl.'/checkouts', array_filter($payload));

        return $response->json('data', [
            'id' => '',
            'url' => '',
            'status' => 'draft',
        ]);
    }

    public function createSubscription(array $params): array
    {
        $payload = [
            'items' => [
                [
                    'price_id' => $params['price_id'] ?? $params['plan_id'],
                    'quantity' => 1,
                ],
            ],
            'customer_email' => $params['customer_email'] ?? '',
            'custom_data' => $params['custom_data'] ?? [],
        ];

        $response = Http::acceptJson()
            ->withHeader('Authorization', 'Bearer '.$this->apiKey)
            ->post($this->baseUrl.'/subscriptions', $payload);

        $data = $response->json('data', []);

        return [
            'id' => (string) ($data['id'] ?? ''),
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }

    public function verifyWebhookSignature(array $params): bool
    {
        $webhookSecret = (string) config('billing.webhooks.providers.paddle.signing_secret');

        if ($webhookSecret === '') {
            return false;
        }

        $eventId = (string) ($params['event_id'] ?? '');
        $occurredAt = (string) ($params['occurred_at'] ?? '');
        $webhookPayload = (string) ($params['webhook_payload'] ?? '');
        $providedSignature = (string) ($params['signature'] ?? '');

        $signaturePayload = "{$eventId}:{$occurredAt}:{$webhookPayload}";
        $expectedSignature = hash_hmac('sha256', $signaturePayload, $webhookSecret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}

