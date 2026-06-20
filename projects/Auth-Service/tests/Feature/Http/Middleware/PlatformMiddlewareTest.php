<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\PlatformMiddleware;
use App\Services\JwtService;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // 1. عمل Mock لخدمة الـ JwtService وحقنها في الـ Container
  $this->jwtServiceMock = $this->mock(JwtService::class);

  // 2. تسجيل مسار وهمي وتطبيق الميدل وير عليه لاختبار دورة الطلب كاملة
  Route::get('/api/test-platform-protected', function () {
    return response()->json([
      'message'      => 'Passed Platform Middleware',
      'auth_user_id' => request()->input('auth_user_id'), // للتأكد من نجاح الـ merge
    ]);
  })->middleware(PlatformMiddleware::class);
});

// =========================================================================
// اختبار حالة: نوع التوكن خاطئ (403 Forbidden) - الأسطر 29-33
// =========================================================================
test('returns 403 when token type is not platform', function () {
  // محاكاة إرجاع توكن بنوع مختلف مثل 'user'
  $this->jwtServiceMock->shouldReceive('validateToken')
    ->once()
    ->with('some-valid-token')
    ->andReturn((object)[
      'type' => 'user', // ليس platform
      'sub'  => 'user-123'
    ]);

  $this->withToken('some-valid-token')
    ->getJson('/api/test-platform-protected')
    ->assertStatus(403)
    ->assertJson(['error' => 'Invalid token type!']);
});

// =========================================================================
// اختبار مسار النجاح: التوكن صحيح ونوعه platform (200 OK) - الأسطر 35-38
// =========================================================================
test('passes request and merges auth_user_id when token type is platform', function () {
  // محاكاة إرجاع توكن بنوع platform صحيح
  $this->jwtServiceMock->shouldReceive('validateToken')
    ->once()
    ->with('correct-platform-token')
    ->andReturn((object)[
      'type' => 'platform',
      'sub'  => 'platform-admin-99'
    ]);

  $this->withToken('correct-platform-token')
    ->getJson('/api/test-platform-protected')
    ->assertStatus(200)
    ->assertJson([
      'message'      => 'Passed Platform Middleware',
      'auth_user_id' => 'platform-admin-99' // تم التحقق من الـ merge بنجاح ✅
    ]);
});
