<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest المراد فحصه لتهيئة بيئة الاختبار
  Route::post('/api/test-change-password', function (ChangePasswordRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير جميع البيانات بشكل صحيح ومطابق للشروط
// =========================================================================
test('validation passes when all data is valid', function () {
  $this->postJson('/api/test-change-password', [
    'current_password'          => 'old_password_123',
    'new_password'              => 'secret_password_123',
    'new_password_confirmation' => 'secret_password_123', // متطابق وأكثر من 8 أحرف
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار فشل التحقق: غياب كلمة المرور الحالية (current_password)
// =========================================================================
test('validation fails when current_password is missing', function () {
  $this->postJson('/api/test-change-password', [
    'new_password'              => 'secret_password_123',
    'new_password_confirmation' => 'secret_password_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['current_password']);
});

// =========================================================================
// 3. اختبار فشل التحقق: غياب كلمة المرور الجديدة (new_password)
// =========================================================================
test('validation fails when new_password is missing', function () {
  $this->postJson('/api/test-change-password', [
    'current_password' => 'old_password_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['new_password']);
});

// =========================================================================
// 4. اختبار فشل التحقق: كلمة المرور الجديدة أقل من 8 أحرف (min:8)
// =========================================================================
test('validation fails when new_password is less than 8 characters', function () {
  $this->postJson('/api/test-change-password', [
    'current_password'          => 'old_password_123',
    'new_password'              => 'short', // 5 أحرف فقط
    'new_password_confirmation' => 'short',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['new_password']);
});

// =========================================================================
// 5. اختبار فشل التحقق: عدم تطابق التأكيد (confirmed)
// =========================================================================
test('validation fails when new_password confirmation does not match', function () {
  $this->postJson('/api/test-change-password', [
    'current_password'          => 'old_password_123',
    'new_password'              => 'secret_password_123',
    'new_password_confirmation' => 'different_password_999', // غير متطابق ❌
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['new_password']);
});
