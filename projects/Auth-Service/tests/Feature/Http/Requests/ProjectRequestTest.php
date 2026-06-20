<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\ProjectRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest لتهيئة بيئة فحص قواعد التحقق
  Route::post('/api/test-project', function (ProjectRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبارات مسار النجاح (Success Paths)
// =========================================================================

test('validation passes when all project data is valid with settings json', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => 'wevo-platform-cms',
    'is_active' => true,
    'settings'  => json_encode(['theme' => 'dark', 'notifications' => true]), // JSON صالح
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

test('validation passes when nullable settings field is missing', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => 'wevo-platform-cms',
    'is_active' => false, // بدون تمرير حقل settings لأنه nullable ✅
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار قواعد الحقل: name
// =========================================================================

test('validation fails when name is missing', function () {
  $this->postJson('/api/test-project', [
    'slug'      => 'wevo-platform-cms',
    'is_active' => true,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name is not a string', function () {
  $this->postJson('/api/test-project', [
    'name'      => ['array-instead-of-string'],
    'slug'      => 'wevo-platform-cms',
    'is_active' => true,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

test('validation fails when name exceeds 255 characters', function () {
  $this->postJson('/api/test-project', [
    'name'      => str_repeat('a', 256),
    'slug'      => 'wevo-platform-cms',
    'is_active' => true,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name']);
});

// =========================================================================
// 3. اختبار قواعد الحقل: slug
// =========================================================================

test('validation fails when slug is missing', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'is_active' => true,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['slug']);
});

test('validation fails when slug is not a string', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => 123456, // تمرير مصفوفة أو قيم تكسر قاعدة الـ string الصارمة في اختبار الـ request
  ])
    ->tap(function () {
      $this->postJson('/api/test-project', [
        'name'      => 'Wevo Platform CMS',
        'slug'      => ['array-instead-of-string'],
        'is_active' => true,
      ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
    });
});

test('validation fails when slug exceeds 255 characters', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => str_repeat('b', 256),
    'is_active' => true,
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['slug']);
});

// =========================================================================
// 4. اختبار قواعد الحقل: is_active
// =========================================================================

test('validation fails when is_active is missing', function () {
  $this->postJson('/api/test-project', [
    'name' => 'Wevo Platform CMS',
    'slug' => 'wevo-platform-cms',
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['is_active']);
});

test('validation fails when is_active is not a boolean', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => 'wevo-platform-cms',
    'is_active' => 'not-a-boolean-string', // نص صريح يكسر الـ boolean
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['is_active']);
});

// =========================================================================
// 5. اختبار قواعد الحقل: settings
// =========================================================================

test('validation fails when settings is present but not a valid json string', function () {
  $this->postJson('/api/test-project', [
    'name'      => 'Wevo Platform CMS',
    'slug'      => 'wevo-platform-cms',
    'is_active' => true,
    'settings'  => '{invalid-json-string', // جيْسون غير مكتمل البنية ❌
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors(['settings']);
});
