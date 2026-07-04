<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\VerifyProjectJoinRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص انضمام المشروع
  Route::post('/api/test-verify-project-join', function (VerifyProjectJoinRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير بيانات صالحة ومطابقة للشروط بالكامل
// =========================================================================
test('validation passes when all join data is valid', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'member@wevo.app',
    'otp'        => '654321', // نص بطول 6 خانات تماماً
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: project_id
// =========================================================================

test('validation fails when project_id is missing', function () {
  $this->postJson('/api/test-verify-project-join', [
    'email' => 'member@wevo.app',
    'otp'   => '654321',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['project_id']);
});

test('validation fails when project_id is not numeric', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 'not-numeric-id',
    'email'      => 'member@wevo.app',
    'otp'        => '654321',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['project_id']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: email
// =========================================================================

test('validation fails when email is missing', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'otp'        => '654321',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

test('validation fails when email format is invalid', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'invalid-email-string',
    'otp'        => '654321',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

// =========================================================================
// 4. اختبار قواعد الحقل: otp
// =========================================================================

test('validation fails when otp is missing', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'member@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

test('validation fails when otp is not a string', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'member@wevo.app',
    'otp'        => [1, 2, 3, 4, 5, 6], // تمرير مصفوفة لكسر الـ string
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

test('validation fails when otp is less than 6 characters', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'member@wevo.app',
    'otp'        => '1234', // 4 خانات فقط تكسر size:6
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});

test('validation fails when otp is more than 6 characters', function () {
  $this->postJson('/api/test-verify-project-join', [
    'project_id' => 45,
    'email'      => 'member@wevo.app',
    'otp'        => '1234567', // 7 خانات تكسر size:6
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['otp']);
});
