<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Payment;
use App\Models\Notification;
use Carbon\Carbon;

class ReminderController extends Controller
{
    // Get all group members and check their contributions
    public function index(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // Get groups created by the logged-in treasurer
            $groups = Group::where('created_by', $request->user()->id)
                ->with('members.user:id,name')
                ->get();

            $members = [];

            foreach ($groups as $group) {

                foreach ($group->members as $membership) {

                    if (!$membership->user) {
                        continue;
                    }

                    // Check successful payment within selected dates
                    $payment = Payment::where('group_id', $group->id)
                        ->where('user_id', $membership->user_id)
                        ->whereRaw('LOWER(status) = ?', ['success'])
                        ->whereBetween('created_at', [
                            $startDate,
                            $endDate
                        ])
                        ->latest()
                        ->first();

                    $members[] = [
                        'user_id' => $membership->user_id,
                        'name' => $membership->user->name,
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'contributed' => $payment ? true : false,
                        'status' => $payment
                            ? 'Contributed'
                            : 'Not Contributed',
                        'amount' => $payment?->amount,
                        'payment_date' => $payment?->created_at,
                    ];
                }
            }

            return response()->json([
                'groups' => $groups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'name' => $group->name
                    ];
                }),
                'members' => $members
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to fetch contribution reminders.'
            ], 500);
        }
    }


    // Send reminder to a member
    public function sendReminder(Request $request)
    {
        $request->validate([
            'group_id' => 'required|integer',
            'user_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {

            // Check that the group belongs to this treasurer
            $group = Group::where('id', $request->group_id)
                ->where('created_by', $request->user()->id)
                ->first();

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found or access denied.'
                ], 404);
            }

            // Check that the member belongs to this group
            $member = GroupMember::where('group_id', $group->id)
                ->where('user_id', $request->user_id)
                ->first();

            if (!$member) {
                return response()->json([
                    'message' => 'Member not found in this group.'
                ], 404);
            }

            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            // Check whether the member has already contributed
            $paid = Payment::where('group_id', $group->id)
                ->where('user_id', $request->user_id)
                ->whereRaw('LOWER(status) = ?', ['success'])
                ->whereBetween('created_at', [
                    $startDate,
                    $endDate
                ])
                ->exists();

            if ($paid) {
                return response()->json([
                    'message' => 'This member has already contributed during this period.'
                ], 422);
            }

            // Create in-app notification
            Notification::create([
                'user_id' => $request->user_id,
                'title' => 'Contribution Reminder',
                'message' => 'Please remember to make your contribution to '
                    . $group->name . '.',
                'type' => 'reminder',
                'is_read' => false
            ]);

            return response()->json([
                'message' => 'Reminder sent successfully.'
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to send reminder.'
            ], 500);
        }
    }
}
