<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\AuthUserMiddleware;
use App\Domains\Auth\Service\AuthServiceClient;
use Illuminate\Http\Request;
use Mockery;

// ─── الحالة الأولى: اختبار عدم إرسال التوكن (بدون أي Mock) ───────────────────
test('it returns 401 unauthorized if no bearer token is provided', function () {
  // 1. Arrange
  $middleware = new AuthUserMiddleware();
  $request = Request::create('/any-route', 'GET');

  // 2. Act
  $response = $middleware->handle($request, function ($req) {
    return response('Next was called'); // لن يتم الوصول إلى هنا
  });

  // 3. Assert
  expect($response->getStatusCode())->toBe(401)
    ->and($response->getContent())->toContain('Unauthorized');
});

// ─── الحالة الثانية: اختبار التوكن الصالح (هنا الـ Mock ضروري للخدمة الخارجية) ───
test('it sets auth_user attribute and proceeds to next request when token is valid', function () {
  // 1. Arrange
  $middleware = new AuthUserMiddleware();
  $request = Request::create('/any-route', 'GET');

  // إضافة توكن وهمي للطلب
  $request->headers->set('Authorization', 'Bearer fake-valid-token-123');

  // 🔥 التعديل هنا: مصفوفة عادية لتطابق الـ Return Type الخاص بالتابع
  $fakeUser = [
    'id' => 1,
    'name' => 'Ahmed'
  ];

  // عمل Mock للخدمة الخارجية
  $authClientMock = Mockery::mock(AuthServiceClient::class);
  $authClientMock->shouldReceive('getUserFromToken')
    ->once()
    ->with('fake-valid-token-123')
    ->andReturn($fakeUser);

  // ربط الـ Mock داخل حاوية لارافيل
  app()->instance(AuthServiceClient::class, $authClientMock);

  // 2. Act
  $response = $middleware->handle($request, function ($req) {
    return response('Next was called');
  });

  // 3. Assert
  expect($response->getContent())->toBe('Next was called')
    ->and($request->attributes->get('auth_user'))->toBe($fakeUser);
});
