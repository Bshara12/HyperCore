<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\InvitationRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص قواعد التحقق
  Route::post('/api/test-invitation', function (InvitationRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير بيانات صالحة تماماً ومطابقة للشروط
// =========================================================================
test('validation passes when all data is valid', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 12,
    'role_id'    => 3,
    'email'      => 'developer@wevo.app',
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: project_id
// =========================================================================

test('validation fails when project_id is missing', function () {
  $this->postJson('/api/test-invitation', [
    'role_id'    => 3,
    'email'      => 'developer@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['project_id']);
});

test('validation fails when project_id is not numeric', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 'string-instead-of-numeric',
    'role_id'    => 3,
    'email'      => 'developer@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['project_id']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: role_id
// =========================================================================

test('validation fails when role_id is missing', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 12,
    'email'      => 'developer@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['role_id']);
});

test('validation fails when role_id is not numeric', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 12,
    'role_id'    => 'string-instead-of-numeric',
    'email'      => 'developer@wevo.app',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['role_id']);
});

// =========================================================================
// 4. اختبار قواعد الحقل: email
// =========================================================================

test('validation fails when email is missing', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 12,
    'role_id'    => 3,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});

test('validation fails when email format is invalid', function () {
  $this->postJson('/api/test-invitation', [
    'project_id' => 12,
    'role_id'    => 3,
    'email'      => 'invalid-email-format', // صيغة غير صالحة للبريد الإلكتروني
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['email']);
});
