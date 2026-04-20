<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\KeyController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ServiceAuthController;
use App\Http\Controllers\UserInfoController;
use Illuminate\Support\Facades\Route;


// Main processes:
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::get('get-all-roles', [OperationController::class, 'getAllRoles']);
Route::get('get-all-permissions', [OperationController::class, 'getAllPermissions']);
Route::post('users/by-ids', [AuthController::class, 'getByIds']);


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
  Route::get('my-profile', [MeController::class, 'myProfile']); //new الوصول إلى بيانات مستخدم عن طريق ال access token
  Route::post('sign-in-project/{projectId}', [ProjectController::class, 'sign_in_project']);
});


// Public Processes:
Route::get('/.well-known/jwks.json', [KeyController::class, 'jwks']);
Route::get('/.well-known/jwks', [KeyController::class, 'index']); // نشر المفتاج العام
Route::post('create-service', [ServiceAuthController::class, 'createService']); // new إنشاء خدمة
Route::post('/service/token', [ServiceAuthController::class, 'token']); // توليد توكن للخدمة


// Services Processes:
//هذا endpoint يجب أن يكون لـ services فقط وليس للمستخدمين.
Route::middleware('service.auth')->group(function () {
  Route::get('/users/{id}', [UserInfoController::class, 'show']);
  Route::get('/me', [MeController::class, 'index']);
  Route::get('/profile/{id}', [MeController::class, 'profile']); // new خدمة تصل إلى مستخدم محدد باستخدام التوكن الخاص بالخدمة و رقم المستخدم id
});
