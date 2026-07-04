<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

// تفعيل تفريغ قاعدة البيانات بسبب وجود قاعدة الـ unique على جدول المستخدمين
uses(RefreshDatabase::class);

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص قواعد التحقق
  Route::post('/api/test-register', function (RegisterRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير بيانات صالحة ومطابقة للشروط بالكامل
// =========================================================================
test('validation passes when all registration data is valid', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'ahmad@wevo.app', // بريد فريد وغير مكرر
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123', // متطابق وأكثر من 8 أحرف
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: name
// =========================================================================

test('validation fails when name is missing', function () {
  $this->postJson('/api/test-register', [
    'email'                 => 'ahmad@wevo.app',
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name is not a string', function () {
  $this->postJson('/api/test-register', [
    'name'                  => ['array-instead-of-string'],
    'email'                 => 'ahmad@wevo.app',
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name exceeds 255 characters', function () {
  $this->postJson('/api/test-register', [
    'name'                  => str_repeat('a', 256), // يتخطى الحد الأقصى
    'email'                 => 'ahmad@wevo.app',
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: email
// =========================================================================

test('validation fails when email is missing', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

test('validation fails when email format is invalid', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'invalid-email-format', // صيغة خاطئة
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

test('validation fails when email is already taken', function () {
  // إنشاء مستخدم مسبقاً بنفس البريد الإلكتروني لكسر قاعدة الـ unique 
  User::factory()->create(['email' => 'existing@wevo.app']);

  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'existing@wevo.app', // بريد مستخدم بالفعل ❌
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'super_secure_pass_123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

// =========================================================================
// 4. اختبار قواعد الحقل: password
// =========================================================================

test('validation fails when password is missing', function () {
  $this->postJson('/api/test-register', [
    'name'  => 'Ahmad Engineer',
    'email' => 'ahmad@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});

test('validation fails when password is not a string', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'ahmad@wevo.app',
    'password'              => [12345678], // مصفوفة لتخطي نوع البيانات النصي
    'password_confirmation' => [12345678],
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});

test('validation fails when password is less than 8 characters', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'ahmad@wevo.app',
    'password'              => 'short1', // 6 أحرف تكسر min:8
    'password_confirmation' => 'short1',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});

test('validation fails when password confirmation does not match', function () {
  $this->postJson('/api/test-register', [
    'name'                  => 'Ahmad Engineer',
    'email'                 => 'ahmad@wevo.app',
    'password'              => 'super_secure_pass_123',
    'password_confirmation' => 'different_pass_999', // غير متطابق ❌
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['password']);
});
