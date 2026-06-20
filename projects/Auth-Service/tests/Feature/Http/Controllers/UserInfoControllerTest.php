<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// تفعيل تهيئة قاعدة البيانات قبل كل فحص
uses(RefreshDatabase::class);

test('show method returns user data when user exists', function () {
  // ✅ تعطيل الميدل وير الخارجي للسماح بالوصول المباشر للكنترولر
  $this->withoutMiddleware();

  // 1. إنشاء مستخدم وهمي باستخدام الـ Factory
  $user = User::factory()->create([
    'email' => 'developer@example.com',
  ]);

  // 2. إرسال طلب GET إلى مسار عرض بيانات المستخدم
  $response = $this->getJson("/api/users/{$user->id}");

  // 3. التأكد من أن الحالة 200 والبيانات المعادة مطابقة تماماً لما في الكنترولر
  $response->assertStatus(200)
    ->assertJson([
      'id'    => $user->id,
      'email' => 'developer@example.com',
    ]);
});

test('show method returns 404 when user does not exist', function () {
  // ✅ تعطيل الميدل وير الخارجي هنا أيضاً لتجنب الـ 401
  $this->withoutMiddleware();

  // 1. إرسال طلب برقم معرف غير موجود في قاعدة البيانات
  $response = $this->getJson('/api/users/999');

  // 2. التأكد من إرجاع كود 404 ورسالة الخطأ المحددة في الكود
  $response->assertStatus(404)
    ->assertJson([
      'error' => 'User not found',
    ]);
});
