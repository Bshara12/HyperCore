<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KeyController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ProjectMembershipController;
use App\Http\Controllers\ServiceAuthController;
use App\Http\Controllers\UserInfoController;
use Illuminate\Support\Facades\Route;

// Main processes:
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:3,1');
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:20,1');

Route::get('get-all-roles', [OperationController::class, 'getAllRoles']);
Route::get('get-all-permissions', [OperationController::class, 'getAllPermissions']);

// Secure processes:
Route::middleware(['auth.jwt'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::get('get-all-users', [OperationController::class, 'getAllUsers']);
    Route::post('assign-role-to-user', [OperationController::class, 'assginRoleToUser']);
    Route::post('remove-role-from-user', [OperationController::class, 'removeRoleFromUser']);
    Route::post('add-permession', [OperationController::class, 'add_permession']);
    Route::post('assign-permession-to-role', [OperationController::class, 'assign_permession_to_role']);
    Route::post('remove-permession-from-role', [OperationController::class, 'remove_permession_from_role']);
    Route::get('/me', [MeController::class, 'index']);
    Route::get('my-profile', [MeController::class, 'myProfile']);

    Route::prefix('operations')->group(function () {
        Route::post('/roles', [OperationController::class, 'createRole']);
        Route::get('/roles', [OperationController::class, 'getRoles']);
        Route::post('/permissions/create', [OperationController::class, 'createPermission']);
        Route::get('/permissions', [OperationController::class, 'getPermissions']);
        Route::post('/projects/{projectId}/assign-role', [OperationController::class, 'assignRoleToUserForProject']);
        Route::post('/projects/{projectId}/remove-role', [OperationController::class, 'removeRoleFromUserForProject']);
    });
});

// Public Processes:
Route::get('/.well-known/jwks.json', [KeyController::class, 'jwks']);
Route::get('/.well-known/jwks', [KeyController::class, 'index']);
Route::post('create-service', [ServiceAuthController::class, 'createService']);
Route::post('/service/token', [ServiceAuthController::class, 'token']);
Route::post('/service/refresh', [ServiceAuthController::class, 'refresh']);

// Services Processes:
Route::middleware('service.auth')->group(function () {
    Route::get('get-service', [ServiceAuthController::class, 'getService']);
    Route::get('/users/{id}', [UserInfoController::class, 'show'])->whereNumber('id');
    Route::get('/profile/{id}', [MeController::class, 'profile'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Internal Service-to-Service Routes — محمية بـ X-Internal-Api-Key فقط
|--------------------------------------------------------------------------
*/
Route::middleware('internal.api')
    ->prefix('internal')
    ->group(function () {
        Route::get('/users/{id}', [MeController::class, 'internalShow'])->whereNumber('id');
        Route::post('/users/by-ids', [AuthController::class, 'getByIds']);

        Route::post('/projects/{projectId}/join', [ProjectMembershipController::class, 'join']);
        Route::get('/projects/{projectId}/members', [ProjectMembershipController::class, 'members']);
        Route::post('/projects/{projectId}/leave', [ProjectMembershipController::class, 'leave']);
    });

Route::get('/ping', function () {
    return response()->json([
        'ok' => true,
        'time' => now(),
    ]);
});
