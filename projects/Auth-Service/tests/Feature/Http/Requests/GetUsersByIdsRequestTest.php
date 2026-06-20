<?php

namespace Tests\Feature\Http\Requests;

use App\Http\Requests\GetUsersByIdsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

// تفعيل تفريغ قاعدة البيانات بعد كل فحص بسبب وجود قاعدة الـ exists 
uses(RefreshDatabase::class);

beforeEach(function () {
  // تسجيل مسار وهمي يستقبل الـ FormRequest المراد فحصه
  Route::post('/api/test-get-users-by-ids', function (GetUsersByIdsRequest $request) {
    return response()->json(['message' => 'Validation Passed']);
  });
});

// =========================================================================
// 1. اختبار مسار النجاح: تمرير مصفوفة معرفات موجودة فعلياً في قاعدة البيانات
// =========================================================================
test('validation passes when all ids are valid and exist in database', function () {
  // إنشاء مستخدمين وهميين في قاعدة البيانات
  $user1 = User::factory()->create();
  $user2 = User::factory()->create();

  $this->postJson('/api/test-get-users-by-ids', [
    'ids' => [$user1->id, $user2->id]
  ])
    ->assertStatus(200)
    ->assertJson(['message' => 'Validation Passed']);
});

// =========================================================================
// 2. اختبار فشل قاعدة: ids.required والرسالة المخصصة لها
// =========================================================================
test('validation fails and returns custom message when ids field is missing', function () {
  $this->postJson('/api/test-get-users-by-ids', [])
    ->assertStatus(422)
    ->assertJsonValidationErrors([
      'ids' => 'The ids field is required.' // الرسالة المخصصة الخاصة بك ✅
    ]);
});

// =========================================================================
// 3. اختبار فشل قاعدة: ids.array والرسالة المخصصة لها
// =========================================================================
test('validation fails and returns custom message when ids is not an array', function () {
  $this->postJson('/api/test-get-users-by-ids', [
    'ids' => 'string-instead-of-array'
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors([
      'ids' => 'The ids field must be an array.' // الرسالة المخصصة الخاصة بك ✅
    ]);
});

// =========================================================================
// 4. اختبار إرسال مصفوفة فارغة: تضرب قاعدة الـ required في لارافل تلقائياً
// =========================================================================
test('validation fails when ids array is empty', function () {
  $this->postJson('/api/test-get-users-by-ids', [
    'ids' => [] // مصفوفة فارغة
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors([
      'ids' => 'The ids field is required.' // لارافل يعيد هذه الرسالة للمصفوفة الفارغة ✅
    ]);
});

// =========================================================================
// 5. اختبار فشل قاعدة: ids.*.integer والرسالة المخصصة لها
// =========================================================================
test('validation fails and returns custom message when an id is not an integer', function () {
  $this->postJson('/api/test-get-users-by-ids', [
    'ids' => ['not-an-integer-string'] // عنصر نصي وليس رقمي
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors([
      'ids.0' => 'Each id must be an integer.' // الرسالة المخصصة للعنصر الأول ✅
    ]);
});

// =========================================================================
// 6. اختبار فشل قاعدة: ids.*.exists والرسالة المخصصة لها
// =========================================================================
test('validation fails and returns custom message when an id does not exist in users table', function () {
  $this->postJson('/api/test-get-users-by-ids', [
    'ids' => [9999] // معرف غير موجود يكسر قاعدة الـ exists
  ])
    ->assertStatus(422)
    ->assertJsonValidationErrors([
      'ids.0' => 'One or more selected users do not exist.' // الرسالة المخصصة للـ exists ✅
    ]);
});
