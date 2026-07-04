<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\CreateServiceRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص قواعد التحقق
  Route::post('/api/test-create-service', function (CreateServiceRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير بيانات صالحة تماماً ومطابقة للشروط
// =========================================================================
test('validation passes when all data is valid', function () {
  $this->postJson('/api/test-create-service', [
    'name'          => 'Core CMS Service',
    'client_secret' => 'super-secure-secret-key-abc-123',
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: name
// =========================================================================

test('validation fails when name is missing', function () {
  $this->postJson('/api/test-create-service', [
    'client_secret' => 'super-secure-secret-key-abc-123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name is not a string', function () {
  $this->postJson('/api/test-create-service', [
    'name'          => ['array-instead-of-string'], // نوع خاطئ
    'client_secret' => 'super-secure-secret-key-abc-123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name exceeds 255 characters', function () {
  $this->postJson('/api/test-create-service', [
    'name'          => str_repeat('a', 256), // 256 حرف لتخطي الحد الأقصى
    'client_secret' => 'super-secure-secret-key-abc-123',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: client_secret
// =========================================================================

test('validation fails when client_secret is missing', function () {
  $this->postJson('/api/test-create-service', [
    'name' => 'Core CMS Service',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['client_secret']);
});

test('validation fails when client_secret is not a string', function () {
  $this->postJson('/api/test-create-service', [
    'name'          => 'Core CMS Service',
    'client_secret' => 123456, // تمرير أرقام بدلاً من string صريح في بعض البيئات الصارمة أو مصفوفة
  ])
    // لضمان كسر قاعدة التعبير النصي بشكل قاطع نمرر مصفوفة
    ->tap(function () {
      $this->postJson('/api/test-create-service', [
        'name'          => 'Core CMS Service',
        'client_secret' => ['not-a-string-array'],
      ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_secret']);
    });
});
