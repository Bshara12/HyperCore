<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\VerifyOTPRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص رمز الـ OTP
  Route::post('/api/test-verify-otp', function (VerifyOTPRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير رمز مكون من 6 خانات نصية تماماً
// =========================================================================
test('validation passes when otp is a valid 6 character string', function () {
  $this->postJson('/api/test-verify-otp', [
    'otp' => '123456', // مطابِق للشروط تماماً
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قاعدة الحقل: required
// =========================================================================
test('validation fails when otp is missing', function () {
  $this->postJson('/api/test-verify-otp', [])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

// =========================================================================
// 3. اختبار قاعدة الحقل: string
// =========================================================================
test('validation fails when otp is not a string', function () {
  $this->postJson('/api/test-verify-otp', [
    'otp' => [1, 2, 3, 4, 5, 6], // تمرير مصفوفة لكسر قاعدة الـ string
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

// =========================================================================
// 4. اختبار قاعدة الحقل: size:6 (الحالة الأولى: أقصر من المطلوب)
// =========================================================================
test('validation fails when otp length is less than 6 characters', function () {
  $this->postJson('/api/test-verify-otp', [
    'otp' => '12345', // 5 خانات فقط ❌
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

// =========================================================================
// 5. اختبار قاعدة الحقل: size:6 (الحالة الثانية: أطول من المطلوب)
// =========================================================================
test('validation fails when otp length is more than 6 characters', function () {
  $this->postJson('/api/test-verify-otp', [
    'otp' => '1234567', // 7 خانات ❌
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});
