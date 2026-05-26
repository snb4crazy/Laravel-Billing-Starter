<?php

namespace App\Http\Controllers\Billing;

use App\Billing\ProviderManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function store(StoreSubscriptionRequest $request, ProviderManager $providerManager): JsonResponse
    {
        $plan = Plan::query()->findOrFail($request->integer('plan_id'));

        $providerResponse = $providerManager
            ->provider($plan->provider)
            ->createSubscription($request->user(), $plan, $request->string('interval')->value());

        $subscription = Subscription::query()->create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'provider' => $plan->provider,
            'provider_subscription_id' => $providerResponse['provider_subscription_id'],
            'status' => $providerResponse['status'] ?? 'incomplete',
        ]);

        return response()->json([
            'data' => $subscription,
        ], Response::HTTP_CREATED);
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ($subscription->user_id !== $user->id && ! $user->isAdmin())) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if ($subscription->status !== 'canceled') {
            $subscription->forceFill([
                'status' => 'canceled',
                'canceled_at' => now(),
            ])->save();
        }

        return response()->json([
            'data' => $subscription->fresh(),
        ]);
    }
}


