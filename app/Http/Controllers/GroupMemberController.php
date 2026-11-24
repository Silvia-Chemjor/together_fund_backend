<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupMemberController extends Controller
{
    // Submit request to join the group
    public function requestToJoin(Request $request){
        $request->validate([
            'code' => 'required|string|exists:groups,code'
        ]);

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $user = Auth::user();
            $group = Group::where('code', $request->code)->first();

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found'
                ], 404);
            }

            if ($group->created_by == $userId) {
                return response()->json([
                    'message' => 'You are already the creator of this group'
                ], 409);
            }

            $existingMember = GroupMember::where('group_id', $group->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingMember) {
                if ($existingMember->status == 'approved') {
                    return response()->json([
                        'message' => 'You are already a member of this group'
                    ], 409);
                }

                if ($existingMember->status == 'pending') {
                    return response()->json([
                        'message' => 'Your join request is already pending'
                    ], 409);
                }

                // Allow the user to submit the request again
                $existingMember->role = 'Member';
                $existingMember->status = 'pending';
                $existingMember->joined_at = null;
                $existingMember->save();

                // Record activity
                Log::create([
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'role' => $user->role ? $user->role->name : 'Unknown',
                    'action' => 'Resubmitted request to join group: ' . $group->name,
                    'type' => 'Group Management',
                    'status' => 'Success'
                ]);

                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'member' => $existingMember
                ], 200);
            }

            $member = new GroupMember();
            $member->group_id = $group->id;
            $member->user_id = $userId;
            $member->role = 'Member';
            $member->status = 'pending';
            $member->joined_at = null;
            $member->save();

            // Record activity
            Log::create([
                'user_id' => $userId,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Requested to join group: ' . $group->name,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Join request submitted successfully',
                'member' => $member
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit join request'
            ], 500);
        }
    }

    // View pending group requests
    public function readGroupRequests($groupId){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $group = Group::find($groupId);

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found'
                ], 404);
            }

            if ($group->created_by != $userId) {
                return response()->json([
                    'message' => 'You are not the treasurer of this group'
                ], 403);
            }

            $members = GroupMember::with('user:id,name,email')
                ->where('group_id', $groupId)
                ->where('status', 'pending')
                ->get();

            return response()->json([
                'message' => 'Requests fetched successfully',
                'members' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group requests'
            ], 500);
        }
    }

    // Approve or reject a join request
    public function updateRequest(Request $request, $id){
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $member = GroupMember::with('group')->find($id);

            if (!$member) {
                return response()->json([
                    'message' => 'Member request not found'
                ], 404);
            }

            if (!$member->group || $member->group->created_by != $userId) {
                return response()->json([
                    'message' => 'You are not allowed to manage this group'
                ], 403);
            }

            if ($member->status != 'pending') {
                return response()->json([
                    'message' => 'This request has already been reviewed'
                ], 409);
            }

            $member->status = $request->status;

            if ($request->status == 'approved') {
                $member->joined_at = now();
            } else {
                $member->joined_at = null;
            }

            $member->save();

            // Record activity
            $user = Auth::user();

            Log::create([
                'user_id' => $userId,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => ucfirst($request->status) . ' join request for group: ' . $member->group->name,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Member request ' . $request->status . ' successfully',
                'member' => $member
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update member request'
            ], 500);
        }
    }

    // View approved members of a group
    public function readGroupMembers($groupId){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $group = Group::where('id', $groupId)
                ->where('created_by', $userId)
                ->first();

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found or you are not its treasurer'
                ], 403);
            }

            $members = GroupMember::with('user:id,name,email,phone,is_active')
                ->where('group_id', $groupId)
                ->where('status', 'approved')
                ->get();

            // Calculate each member's contributions
            $treasurerExists = false;

            foreach ($members as $member) {

                $member->total_contributed = DB::table('payments')
                    ->where('user_id', $member->user_id)
                    ->where('group_id', $groupId)
                    ->whereIn('status', ['Success', 'Successful'])
                    ->sum('amount');

                if ($member->user && $member->user->is_active) {
                    $member->status = 'Active';
                } else {
                    $member->status = 'Inactive';
                }

                if ($member->user_id == $userId) {
                    $treasurerExists = true;
                }
            }

            // Add the treasurer if they are not already in the list
            if (!$treasurerExists) {
                $treasurer = User::find($userId);

                if ($treasurer) {
                    $treasurer->total_contributed = DB::table('payments')
                        ->where('user_id', $userId)
                        ->where('group_id', $groupId)
                        ->whereIn('status', ['Success', 'Successful'])
                        ->sum('amount');

                    if ($treasurer->is_active) {
                        $treasurer->status = 'Active';
                    } else {
                        $treasurer->status = 'Inactive';
                    }

                    $members->push($treasurer);
                }
            }

            return response()->json([
                'message' => 'Group members fetched successfully',
                'members' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group members'
            ], 500);
        }
    }

    // View all group members
    public function readAllMembers(){
        try {
            $members = GroupMember::with([
                'group:id,name',
                'user:id,name,email'
            ])->get();

            return response()->json([
                'members' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch members'
            ], 500);
        }
    }

    // View one group member
    public function readGroupMember($id){
        try {
            $member = GroupMember::with([
                'group:id,name',
                'user:id,name,email'
            ])->find($id);

            if (!$member) {
                return response()->json([
                    'message' => 'Member not found'
                ], 404);
            }

            return response()->json([
                'member' => $member
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch member'
            ], 500);
        }
    }

    // Remove a member from a group
    public function deleteGroupMember($id){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $member = GroupMember::with(['group', 'user'])->find($id);

            if (!$member) {
                return response()->json([
                    'message' => 'Member not found'
                ], 404);
            }

            if (!$member->group || $member->group->created_by != $userId) {
                return response()->json([
                    'message' => 'You are not allowed to remove this member'
                ], 403);
            }

            // Save details before deleting
            $memberName = $member->user ? $member->user->name : 'Unknown member';
            $groupName = $member->group->name;

            $member->delete();

            // Record activity
            $user = Auth::user();

            Log::create([
                'user_id' => $userId,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Removed ' . $memberName . ' from group: ' . $groupName,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Member removed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove member'
            ], 500);
        }
    }

    // View groups joined by the logged-in user
    public function myJoinedGroups(){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $members = GroupMember::with('group:id,name')
                ->where('user_id', $userId)
                ->where('status', 'approved')
                ->get();

            $groups = [];

            foreach ($members as $member) {
                if ($member->group) {
                    $groups[] = [
                        'id' => $member->group->id,
                        'name' => $member->group->name
                    ];
                }
            }

            return response()->json([
                'message' => 'Groups fetched successfully',
                'groups' => $groups
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch joined groups'
            ], 500);
        }
    }


    public function leaveGroup($groupId){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            // Find the logged-in user's membership in this group
            $member = GroupMember::with(['group', 'user'])
                ->where('group_id', $groupId)
                ->where('user_id', $userId)
                ->first();

            if (!$member) {
                return response()->json([
                    'message' => 'You are not a member of this group'
                ], 404);
            }

            $groupName = $member->group
                ? $member->group->name
                : 'Unknown group';

            $user = Auth::user();

            // Remove only the logged-in user's membership
            $member->delete();

            // Record activity
            Log::create([
                'user_id' => $userId,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Left group: ' . $groupName,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'You have left the group successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to leave group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
