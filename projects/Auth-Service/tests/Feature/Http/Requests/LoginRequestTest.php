<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص قواعد التحقق
  Route::post('/api/test-login', function (LoginRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير بيانات صالحة تماماً ومطابقة للشروط
// =========================================================================
test('validation passes when all login data is valid', function () {
  $this->postJson('/api/test-login', [
    'identifier' => 'user_or_email@wevo.app',
    'password'   => 'secure_password_123', // أكثر من 8 أحرف ونصية
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: identifier
// =========================================================================

test('validation fails when identifier is missing', function () {
  $this->postJson('/api/test-login', [
    'password' => 'secure_password_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['identifier']);
});

test('validation fails when identifier is not a string', function () {
  $this->postJson('/api/test-login', [
    'identifier' => ['array-instead-of-string'], // تمرير مصفوفة لكسر قاعدة الـ string
    'password'   => 'secure_password_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['identifier']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: password
// =========================================================================

test('validation fails when password is missing', function () {
  $this->postJson('/api/test-login', [
    'identifier' => 'user_or_email@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});

test('validation fails when password is not a string', function () {
  $this->postJson('/api/test-login', [
    'identifier' => 'user_or_email@wevo.app',
    'password'   => ['array-instead-of-string'], // تمرير مصفوفة
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});

test('validation fails when password is less than 8 characters', function () {
  $this->postJson('/api/test-login', [
    'identifier' => 'user_or_email@wevo.app',
    'password'   => 'short1', // 6 أحرف فقط تكسر min:8
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});
