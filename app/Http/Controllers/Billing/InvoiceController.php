<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Invoice::query()->latest('id');

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'data' => $query->paginate(15),
        ]);
    }
}


