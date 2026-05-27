<?php

namespace App\Http\Controllers\Billing;

use App\Billing\ProviderManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CreateCheckoutSessionRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class CheckoutSessionController extends Controller
{
    public function store(CreateCheckoutSessionRequest $request, ProviderManager $providerManager): JsonResponse
    {
        $plan = null;

        if ($request->filled('plan_id')) {
            $plan = Plan::query()->findOrFail($request->integer('plan_id'));
        }

        $session = $providerManager
            ->provider($plan?->provider)
            ->createCheckoutSession($request->user(), $plan, $request->validated());

        return response()->json([
            'data' => $session,
        ], 201);
    }
}

