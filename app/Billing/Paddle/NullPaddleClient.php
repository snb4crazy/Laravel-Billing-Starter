<?php

namespace App\Billing\Paddle;

use App\Billing\Contracts\PaddleClientInterface;
use Illuminate\Support\Str;

class NullPaddleClient implements PaddleClientInterface
{
    public function createCheckout(array $params): array
    {
        $returnUrl = (string) ($params['success_url'] ?? config('app.url'));

        return [
            'id' => 'CHECKOUT-NULL-'.Str::upper(Str::random(10)),
            'url' => rtrim($returnUrl, '/').'?stub_paddle_checkout=1',
            'status' => 'draft',
        ];
    }

    public function createSubscription(array $params): array
    {
        return [
            'id' => 'SUB-NULL-'.Str::upper(Str::random(10)),
            'status' => 'active',
        ];
    }

    public function verifyWebhookSignature(array $params): bool
    {
        return false;
    }
}

