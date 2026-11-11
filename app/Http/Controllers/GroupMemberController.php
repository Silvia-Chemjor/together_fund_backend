<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupMemberController extends Controller
{                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           
    public function requestToJoin(Request $request){
        $validated = $request->validate([
            'code' => 'required|string|exists:groups,code'
        ]);

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'Please login first'], 401);
            }

            $group = Group::where('code', $validated['code'])->first();

            if (!$group) {
                return response()->json(['message' => 'Group not found'], 404);
            }

            if ((int) $group->created_by === (int) $userId) {
                return response()->json([
                    'message' => 'You are already the creator of this group'
                ], 409);
            }

            $existingMember = GroupMember::where('group_id', $group->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingMember) {
                if ($existingMember->status === 'approved') {
                    return response()->json([
                        'message' => 'You are already a member of this group'
                    ], 409);
                }

                if ($existingMember->status === 'pending') {
                    return response()->json([
                        'message' => 'Your join request is already pending'
                    ], 409);
                }

                $existingMember->update([
                    'role' => 'Member',
                    'status' => 'pending',
                    'joined_at' => null
                ]);

                return response()->json([
                    'message' => 'Join request submitted successfully',
                    'member' => $existingMember
                ], 200);
            }

            $member = GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $userId,
                'role' => 'Member',
                'status' => 'pending',
                'joined_at' => null
            ]);

            return response()->json([
                'message' => 'Join request submitted successfully',
                'member' => $member
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit join request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function readGroupRequests($groupId){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'Please login first'], 401);
            }

            $group = Group::find($groupId);

            if (!$group) {
                return response()->json(['message' => 'Group not found'], 404);
            }

            if ((int) $group->created_by !== (int) $userId) {
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
                'message' => 'Failed to fetch group requests',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateRequest(Request $request, $id){
        $validated = $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'Please login first'], 401);
            }

            $member = GroupMember::with('group')->find($id);

            if (!$member) {
                return response()->json([
                    'message' => 'Member request not found'
                ], 404);
            }

            if (!$member->group ||
                (int) $member->group->created_by !== (int) $userId) {
                return response()->json([
                    'message' => 'You are not allowed to manage this group'
                ], 403);
            }

            if ($member->status !== 'pending') {
                return response()->json([
                    'message' => 'This request has already been reviewed'
                ], 409);
            }

            $member->status = $validated['status'];
            $member->joined_at = $validated['status'] === 'approved'
                ? now()
                : null;

            $member->save();

            return response()->json([
                'message' => 'Member request ' . $validated['status'] . ' successfully',
                'member' => $member
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update member request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function readGroupMembers($groupId){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            // Confirm that the logged-in treasurer owns this group
            $group = Group::where('id', $groupId)
                ->where('created_by', $userId)
                ->first();

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found or you are not its treasurer'
                ], 403);
            }

            // Get approved group members and their user details
            $members = GroupMember::with('user:id,name,email,phone,is_active')
                ->where('group_id', $groupId)
                ->where('status', 'approved')
                ->get();

            // Calculate each member's successful contributions
            $members->each(function ($member) use ($groupId) {
                $member->total_contributed = DB::table('payments')
                    ->where('user_id', $member->user_id)
                    ->where('group_id', $groupId)
                    ->whereIn('status', ['Success', 'Successful'])
                    ->sum('amount');

                $member->status = $member->user?->is_active
                    ? 'Active'
                    : 'Inactive';
            });

            // Include the treasurer if they are not already in the member list
            $treasurerExists = $members->contains(function ($member) use ($userId) {
                return (int) $member->user_id === (int) $userId;
            });

            if (!$treasurerExists) {
                $treasurer = \App\Models\User::find($userId);

                if ($treasurer) {
                    $treasurer->total_contributed = DB::table('payments')
                        ->where('user_id', $userId)
                        ->where('group_id', $groupId)
                        ->whereIn('status', ['Success', 'Successful'])
                        ->sum('amount');

                    $treasurer->status = $treasurer->is_active
                        ? 'Active'
                        : 'Inactive';

                    $members->push($treasurer);
                }
            }

            return response()->json([
                'message' => 'Group members fetched successfully',
                'members' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function readAllMembers(){
        try {
            $members = GroupMember::with([
                'group:id,name',
                'user:id,name,email'
            ])->get();

            return response()->json(['members' => $members], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function readGroupMember($id){
        try {
            $member = GroupMember::with([
                'group:id,name',
                'user:id,name,email'
            ])->find($id);

            if (!$member) {
                return response()->json(['message' => 'Member not found'], 404);
            }

            return response()->json(['member' => $member], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteGroupMember($id){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'Please login first'], 401);
            }

            $member = GroupMember::with('group')->find($id);

            if (!$member) {
                return response()->json(['message' => 'Member not found'], 404);
            }

            if (!$member->group ||
                (int) $member->group->created_by !== (int) $userId) {
                return response()->json([
                    'message' => 'You are not allowed to remove this member'
                ], 403);
            }

            $member->delete();

            return response()->json([
                'message' => 'Member removed successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove member',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function myJoinedGroups(){
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'message' => 'Please login first'
                ], 401);
            }

            $groups = DB::table('groups')
                ->join('group_members', 'groups.id', '=', 'group_members.group_id')
                ->where('group_members.user_id', $userId)
                ->where('group_members.status', 'approved')
                ->select('groups.id', 'groups.name')
                ->distinct()
                ->get();

            return response()->json([
                'message' => 'Groups fetched successfully',
                'groups' => $groups
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch joined groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}