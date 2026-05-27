<?php

namespace App\Billing\PayPal;

use App\Billing\Contracts\PayPalClientInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalHttpClient implements PayPalClientInterface
{
    private ?string $accessToken = null;

    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
    ) {
    }

    public function createCheckoutOrder(array $params): array
    {
        $response = $this->request('post', '/v2/checkout/orders', $params);

        return [
            'id' => (string) Arr::get($response, 'id', ''),
            'status' => (string) Arr::get($response, 'status', ''),
            'approve_url' => (string) $this->extractLink($response, 'approve'),
        ];
    }

    public function createSubscription(array $params): array
    {
        $response = $this->request('post', '/v1/billing/subscriptions', $params);

        return [
            'id' => (string) Arr::get($response, 'id', ''),
            'status' => (string) Arr::get($response, 'status', ''),
            'approve_url' => $this->extractLink($response, 'approve'),
        ];
    }

    public function verifyWebhookSignature(array $params): bool
    {
        $response = $this->request('post', '/v1/notifications/verify-webhook-signature', $params);

        return strtoupper((string) Arr::get($response, 'verification_status', '')) === 'SUCCESS';
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $payload): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->send($method, $uri, [
                'json' => $payload,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('PayPal API request failed: '.$response->status().' '.$response->body());
        }

        /** @var array<string, mixed> $json */
        $json = $response->json();

        return $json;
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && $this->accessTokenExpiresAt > time() + 30) {
            return $this->accessToken;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->acceptJson()
            ->post('/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('PayPal OAuth token request failed: '.$response->status().' '.$response->body());
        }

        $token = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 0);

        if ($token === '' || $expiresIn <= 0) {
            throw new RuntimeException('PayPal OAuth token response is invalid.');
        }

        $this->accessToken = $token;
        $this->accessTokenExpiresAt = time() + $expiresIn;

        return $this->accessToken;
    }

    private function extractLink(array $payload, string $rel): ?string
    {
        $links = Arr::get($payload, 'links', []);

        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link)) {
                continue;
            }

            if (($link['rel'] ?? null) === $rel && isset($link['href']) && is_string($link['href'])) {
                return $link['href'];
            }
        }

        return null;
    }
}

