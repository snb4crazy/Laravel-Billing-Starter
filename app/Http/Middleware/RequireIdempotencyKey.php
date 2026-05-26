<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');

        if ($idempotencyKey === '' || Str::length($idempotencyKey) > 255) {
            return new JsonResponse([
                'message' => 'A valid Idempotency-Key header is required.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $scope = implode('|', [
            (string) optional($request->user())->getAuthIdentifier(),
            $request->method(),
            $request->path(),
            hash('sha256', $request->getContent()),
            $idempotencyKey,
        ]);

        $cacheKey = 'billing:idempotency:'.hash('sha256', $scope);
        $ttl = max((int) config('billing.idempotency.ttl_seconds', 600), 60);

        if (! Cache::add($cacheKey, now()->toIso8601String(), $ttl)) {
            return new JsonResponse([
                'message' => 'Duplicate request detected for this Idempotency-Key.',
            ], Response::HTTP_CONFLICT);
        }

        return $next($request);
    }
}

