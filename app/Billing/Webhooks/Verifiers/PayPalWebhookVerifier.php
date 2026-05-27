<?php

namespace App\Billing\Webhooks\Verifiers;

use App\Billing\Contracts\PayPalClientInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayPal webhook verifier using PayPal's verify-webhook-signature endpoint.
 *
 * Note: In this starter, the second parameter passed to verify() is expected
 * to be the PayPal webhook ID. For compatibility with existing config shape,
 * we read it from billing.webhooks.providers.paypal.signing_secret.
 */
class PayPalWebhookVerifier implements WebhookVerifier
{
    public function __construct(private readonly PayPalClientInterface $payPal)
    {
    }

    public function verify(Request $request, string $secret): void
    {
        $webhookId = $secret;

        if ($webhookId === '') {
            abort(Response::HTTP_SERVICE_UNAVAILABLE, 'PayPal webhook ID is not configured.');
        }

        $transmissionId = (string) $request->header('Paypal-Transmission-Id', '');
        $transmissionTime = (string) $request->header('Paypal-Transmission-Time', '');
        $certUrl = (string) $request->header('Paypal-Cert-Url', '');
        $authAlgo = (string) $request->header('Paypal-Auth-Algo', '');
        $transmissionSig = (string) $request->header('Paypal-Transmission-Sig', '');

        if (
            $transmissionId === '' ||
            $transmissionTime === '' ||
            $certUrl === '' ||
            $authAlgo === '' ||
            $transmissionSig === ''
        ) {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing PayPal webhook verification headers.');
        }

        $isValid = $this->payPal->verifyWebhookSignature([
            'transmission_id' => $transmissionId,
            'transmission_time' => $transmissionTime,
            'cert_url' => $certUrl,
            'auth_algo' => $authAlgo,
            'transmission_sig' => $transmissionSig,
            'webhook_id' => $webhookId,
            'webhook_event' => $request->json()->all(),
        ]);

        if (! $isValid) {
            abort(Response::HTTP_UNAUTHORIZED, 'PayPal webhook signature verification failed.');
        }
    }
}


