<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CheckPermission;
use Illuminate\Http\Request;

// ─── الحالة الأولى: المستخدم يملك الصلاحية المطلوبة ───────────────────────────
test('it allows request to proceed if user has the required permission', function () {
  // 1. Arrange
  $middleware = new CheckPermission();
  $request = Request::create('/any-route', 'POST');

  // نقوم بحقن بيانات المستخدم وصلاحياته داخل الـ Request مباشرة بدون Mock
  $request->attributes->set('auth_user', [
    'id' => 1,
    'name' => 'Ahmed',
    'permissions' => ['payment.refund', 'payment.view']
  ]);

  // 2. Act: نمرر الصلاحية المطلوبة 'payment.refund' كمعامل ثالث للـ Middleware
  $response = $middleware->handle($request, function ($req) {
    return response('Next was called'); // نتوقع الوصول إلى هنا بنجاح
  }, 'payment.refund');

  // 3. Assert
  expect($response->getContent())->toBe('Next was called');
});

// ─── الحالة الثانية: المستخدم لا يملك الصلاحية المطلوبة ────────────────────────
test('it returns 403 forbidden if user does not have the required permission', function () {
  // 1. Arrange
  $middleware = new CheckPermission();
  $request = Request::create('/any-route', 'POST');

  // مستخدم يملك صلاحية العرض فقط وليس الاسترجاع
  $request->attributes->set('auth_user', [
    'id' => 1,
    'name' => 'Ahmed',
    'permissions' => ['payment.view']
  ]);

  // 2. Act: نطلب صلاحية الاسترجاع وهي غير موجودة عنده
  $response = $middleware->handle($request, function ($req) {
    return response('Next was called'); // لن يتم الوصول إلى هنا
  }, 'payment.refund');

  // 3. Assert
  expect($response->getStatusCode())->toBe(403)
    ->and($response->getContent())->toContain('Forbidden');
});
