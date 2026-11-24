<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Log;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    // Register a new user
    public function register(Request $request){
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string|max:15|unique:users,phone',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:3|confirmed'
        ]);

        try {

            // Find the default USER role
            $role = Role::where('name', 'USER')->first();

            if (!$role) {
                return response()->json([
                    'message' => 'Default USER role not found. Please check the roles table.'
                ], 500);
            }

            $user = new User();
            $user->name = $request->name;
            $user->phone = $request->phone;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);

            // Assign USER role automatically
            $user->role_id = $role->id;

            $user->save();

            // Record successful registration
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $role->name,
                'action' => 'Registered an account',
                'type' => 'Authentication',
                'status' => 'Success'
            ]);

            // Send email verification link
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(8),
                [
                    'id' => $user->id,
                    'hash' => sha1($user->email)
                ]
            );

            $user->notify(new VerifyEmailNotification($signedUrl));

            return response()->json([
                'message' => 'A verification link has been sent to your email address'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Login user
    public function login(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:3',
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {

                // Record failed login attempt
                Log::create([
                    'user_id' => $user ? $user->id : null,
                    'user_name' => $user ? $user->name : 'Unknown user',
                    'role' => $user && $user->role ? $user->role->name : 'Unknown',
                    'action' => 'Failed login attempt',
                    'type' => 'Authentication',
                    'status' => 'Failed'
                ]);

                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            if (!$user->is_active) {

                // Record inactive account login attempt
                Log::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'role' => $user->role ? $user->role->name : 'Unassigned',
                    'action' => 'Login attempt on inactive account',
                    'type' => 'Authentication',
                    'status' => 'Failed'
                ]);

                return response()->json([
                    'message' => 'Your account is not activated, please verify your email'
                ]);
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            // Record successful login
            Log::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'role' => $user->role ? $user->role->name : 'Unassigned',
                'action' => 'Logged into the system',
                'type' => 'Authentication',
                'status' => 'Success'
            ]);

            return response()->json([
                'message' => 'Login successful!',
                'user' => $user,
                'role' => $user->role,
                'abilities' => $user->abilities(),
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to login user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Logout user
    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();

        return response()->json('Logout success');
    }

    // Get logged-in user information
    public function userInfo(Request $request){
    try {
        $user = $request->user()->load('role');

        return response()->json([
            'user' => $user,
            'abilities' => $user->abilities()
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error getting user info.',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
