<?php

namespace App\Http\Controllers;

use App\Models\Group;
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
            // Check whether the user is a treasurer
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

            // Generate a unique group code
            do {
                $code = strtoupper(Str::random(8));
            } while (Group::where('code', $code)->exists());

            $group->code = $code;
            $group->save();

            return response()->json([
                'message' => 'Group created successfully',
                'group' => $group
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create group',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function readAll(){
        try {
            $groups = Group::with('creator:id,name,email')
                ->withCount([
                    'members as number' => function ($query) {
                        $query->where('status', 'approved');
                    }
                ])
                ->get()
                ->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'group' => $group->name,
                        'plan' => $group->premium ? $group->premium->plan : 'Free',
                        'number' => $group->number,
                        'created_by' => $group->creator
                    ];
                });

            return response()->json([
                'message' => 'Groups fetched successfully',
                'groups' => $groups
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'message' => 'Failed to fetch your groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'message' => 'Failed to fetch group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

            return response()->json([
                'message' => 'Group updated successfully',
                'group' => $group
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

            $group->delete();

            return response()->json([
                'message' => 'Group deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete group',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
