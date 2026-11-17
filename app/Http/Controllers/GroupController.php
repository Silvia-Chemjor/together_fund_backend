<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function createGroup(Request $request){
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:200'
        ]);

        try {
            $user = $request->user();

            if (!$user || !$user->role || $user->role->name !== 'TREASURER') {
                return response()->json([
                    'message' => 'Only treasurers can create groups'
                ], 403);
            }

            $group = new Group();
            $group->name = $request->name;
            $group->description = $request->description;
            $group->created_by = $user->id;

            // Generate unique group code
            do {
                $code = strtoupper(Str::random(8));
            } while (Group::where('code', $code)->exists());

            $group->code = $code;
            $group->save();
            // Automatically add the Treasurer as a group member
            GroupMember::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'Treasurer',
                'status' => 'approved',
                'joined_at' => now(),
            ]);

            // Record group creation
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role->name,
                'action' => 'Created group: ' . $group->name,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Group created successfully',
                'group' => $group
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create group'
            ], 500);
        }
    }

    // Read all groups
    public function readAll(){
        try {
            $groups = Group::all();
            $groupList = [];

            foreach ($groups as $group) {
                $number = $group->members()
                    ->where('status', 'approved')
                    ->count();

                $groupList[] = [
                    'id' => $group->id,
                    'group' => $group->name,
                    'number' => $number,
                    'created_by' => $group->creator
                ];
            }

            return response()->json([
                'message' => 'Groups fetched successfully',
                'groups' => $groupList
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch groups'
            ], 500);
        }
    }

    // Fetch groups created by treasurer
    public function readMyGroups(Request $request){
        try {
            $user = $request->user();

            if (!$user || !$user->role || $user->role->name !== 'TREASURER') {
                return response()->json([
                    'message' => 'Only treasurers can view their groups'
                ], 403);
            }

            $groups = Group::where('created_by', $user->id)
                ->select('id', 'name', 'code', 'description', 'created_by', 'created_at')
                ->get();

            return response()->json([
                'message' => 'Groups fetched successfully',
                'groups' => $groups
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch your groups'
            ], 500);
        }
    }

    // Fetch one group
    public function readGroup($id){
        try {
            $group = Group::with('creator:id,name,email')->find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found'
                ], 404);
            }

            return response()->json([
                'group' => $group
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch group'
            ], 500);
        }
    }

    // Update a group
    public function updateGroup($id, Request $request){
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:200'
        ]);

        try {
            $user = $request->user();

            $group = Group::find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found'
                ], 404);
            }

            if (!$user || $group->created_by != $user->id) {
                return response()->json([
                    'message' => 'You are not allowed to update this group'
                ], 403);
            }

            $group->name = $request->name;
            $group->description = $request->description;
            $group->save();

            // Record group update
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Updated group: ' . $group->name,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Group updated successfully',
                'group' => $group
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update group'
            ], 500);
        }
    }

    // Delete a group
    public function deleteGroup($id, Request $request){
        try {
            $user = $request->user();

            $group = Group::find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Group not found'
                ], 404);
            }

            if (!$user || $group->created_by != $user->id) {
                return response()->json([
                    'message' => 'You are not allowed to delete this group'
                ], 403);
            }

            // Save group name before deleting
            $groupName = $group->name;

            $group->delete();

            // Record group deletion
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unknown',
                'action' => 'Deleted group: ' . $groupName,
                'type' => 'Group Management',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Group deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete group'
            ], 500);
        }
    }
}
