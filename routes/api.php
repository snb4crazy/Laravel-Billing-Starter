<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Billing\BillingPlanController;
use App\Http\Controllers\Billing\CheckoutSessionController;
use App\Http\Controllers\Billing\InvoiceController;
use App\Http\Controllers\Billing\PaymentController;
use App\Http\Controllers\Billing\SubscriptionController;
use App\Http\Controllers\Billing\WebhookController;
use Illuminate\Support\Facades\Route;

// --------------------------------------------------------------------------
// Authentication (Sanctum bearer token)
// --------------------------------------------------------------------------
Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// --------------------------------------------------------------------------
// Billing
// --------------------------------------------------------------------------
Route::prefix('billing')->group(function (): void {
    Route::post('/webhooks/{provider}', [WebhookController::class, 'handle'])
        ->middleware(['throttle:webhooks', 'webhook.signature']);

    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::get('/plans', [BillingPlanController::class, 'index']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/invoices', [InvoiceController::class, 'index']);

        Route::post('/checkout/session', [CheckoutSessionController::class, 'store'])
            ->middleware('idempotency');

        Route::post('/subscriptions', [SubscriptionController::class, 'store'])
            ->middleware('idempotency');

        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->middleware('idempotency');
    });
});

