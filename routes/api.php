<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupMemberController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ResendVerificationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//public routes
Route::post('/register_user', [AuthController::class, 'register']);
Route::post('/login_user', [AuthController::class, 'login']);
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
            ->name('verification.verify')
            ->middleware(['signed', 'throttle:6,1']);

Route::post('email/resend', [ResendVerificationController::class, 'resend'])
             ->middleware(['throttle:6,1']);

Route::post('/mpesa/callback', [MpesaController::class, 'callback']);

//Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',[AuthController::class, 'userInfo']);
    Route::get('/read-logs', [LogController::class, 'readAll']);

    Route::get('/mpesa/token', [MpesaController::class, 'getToken']);
    Route::post('/mpesa/stk-push', [MpesaController::class, 'stkPush']);
    Route::post('/mpesa/stk-push/query', [MpesaController::class, 'stkPushQuery']);
    
    Route::post('/logout_user', [AuthController::class, 'logout']);

    Route::post('/create-role',[RoleController::class,'createRole']);
    Route::get('/read-roles',[RoleController::class,'readAll']);
    Route::get('/read-role/{id}',[RoleController::class, 'readRole']);
    Route::post('/update-role/{id}',[RoleController::class, 'updateRole']);
    Route::delete('/delete-role/{id}',[RoleController::class, 'deleteRole']);

    Route::post('/create-user',[UserController::class,'createUser']);
    Route::get('/read-users',[UserController::class,'readAll']);
    Route::get('/read-user/{id}',[UserController::class, 'read']);
    Route::post('/update-user/{id}',[UserController::class, 'updateUser']);
    Route::delete('/delete-user/{id}',[UserController::class, 'deleteUser']);
    Route::post('/update-user-role/{id}', [UserController::class, 'updateUserRole']);

    Route::post('/create-group', [GroupController::class, 'createGroup']);
    Route::get('/read-groups', [GroupController::class, 'readAll']);
    Route::get('/my-groups', [GroupController::class, 'readMyGroups']);
    Route::get('/read-group/{id}', [GroupController::class, 'readGroup']);
    Route::put('/update-group/{id}', [GroupController::class, 'updateGroup']);
    Route::delete('/delete-group/{id}', [GroupController::class, 'deleteGroup']);

    Route::post('/group-members/request', [GroupMemberController::class, 'requestToJoin']);
    Route::get('/groups/{groupId}/requests', [GroupMemberController::class, 'readGroupRequests']);
    Route::post('/group-members/{id}/review', [GroupMemberController::class, 'updateRequest']);
    Route::get('/groups/{groupId}/members', [GroupMemberController::class, 'readGroupMembers']);
    Route::get('/read-group-members', [GroupMemberController::class, 'readAllMembers']);
    Route::get('/read-group-member/{id}', [GroupMemberController::class, 'readGroupMember']);
    Route::delete('/delete-group-member/{id}', [GroupMemberController::class, 'deleteGroupMember']);
    Route::delete('/leave-group/{groupId}', [GroupMemberController::class, 'leaveGroup']);
    Route::get('/my-joined-groups', [GroupMemberController::class, 'myJoinedGroups']);

    Route::get('/contributions', [PaymentController::class, 'readAll']);
    Route::get('/contribution/{id}', [PaymentController::class, 'read']);
    Route::delete('/delete-payment/{id}', [PaymentController::class, 'deletePayment']);      
    Route::get('/my-contributions', [PaymentController::class, 'myContributions']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::get('/reminders', [ReminderController::class,'index']);
    Route::post('/send', [ReminderController::class,'sendReminder']);

    });


