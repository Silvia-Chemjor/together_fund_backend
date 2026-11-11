<?php

namespace App\Http\Controllers;

use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WithdrawController extends Controller
{
    // Create a withdrawal request
    public function store(Request $request){
        $validated = $request->validate([
            'phone_number' => [
                'required',
                'regex:/^(?:254|0|\+254)7\d{8}$/',
            ],
            'amount' => 'required|numeric|min:1|max:1000000',
            'reason' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $phone = $validated['phone_number'];

        // Normalize Kenyan phone number
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '+254')) {
            $phone = substr($phone, 1);
        }

        $withdraw = Withdraw::create([
            'treasurer_id' => $user->id,
            'phone_number' => $phone,
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'Pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return response()->json([
            'message' => 'Withdrawal request created successfully.',
            'withdraw' => $withdraw,
        ], 201);
    }

    // Get the authenticated user's withdrawal requests
    public function index(Request $request){
        $withdraws = Withdraw::where(
            'treasurer_id',
            $request->user()->id
        )->latest()->get();

        return response()->json([
            'withdraws' => $withdraws,
        ], 200);
    }

    // Get one withdrawal request
    public function show(Request $request, $id){
        $withdraw = Withdraw::where(
            'treasurer_id',
            $request->user()->id
        )->findOrFail($id);

        return response()->json([
            'withdraw' => $withdraw,
        ], 200);
    }
}
