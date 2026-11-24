<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Payment;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    // View all payments
    public function readAll(Request $request)
{
    try {
        $user = $request->user();

        // Get groups created by the logged-in treasurer
        $groups = Group::where('created_by', $user->id)
            ->with([
                'members.user:id,name,email,phone'
            ])
            ->get();

        // Get payments belonging to those groups
        $payments = Payment::with([
            'user:id,name',
            'group:id,name'
        ])
        ->whereIn('group_id', $groups->pluck('id'))
        ->latest()
        ->get();

        return response()->json([
            'groups' => $groups,
            'payments' => $payments
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to fetch contributions.'
        ], 500);
    }
}

    // View one payment
    public function read($id)
    {
        try {
            $payment = Payment::where('id', $id)->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'Payment not found.'
                ], 404);
            }

            return response()->json([
                'payment' => $payment
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete a payment record
    public function deletePayment($id)
    {
        try {
            $payment = Payment::with([
                'user:id,name',
                'group:id,name'
            ])->where('id', $id)->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'Payment not found.'
                ], 404);
            }

            // Get the logged-in admin
            $user = Auth::user();

            // Save payment details before deleting
            $memberName = $payment->user ? $payment->user->name : 'Unknown member';
            $groupName = $payment->group ? $payment->group->name : 'Unknown group';

            $payment->delete();

            // Record activity
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Deleted contribution record for ' . $memberName . ' in group: ' . $groupName,
                'type' => 'Contribution',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Payment deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete payment.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // View contributions belonging to the logged-in user
    public function myContributions(Request $request)
    {
        try {
            $payments = Payment::with('group')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get();

            return response()->json([
                'message' => 'Contributions fetched successfully.',
                'contributions' => $payments
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch contributions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
