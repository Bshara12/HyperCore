<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\JwtMiddleware;
use App\Services\JwtService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
  // 1. بناء جدول الجلسات في الذاكرة لمطابقة فحص الميدل وير
  Schema::dropIfExists('my_sessions');
  Schema::create('my_sessions', function ($table) {
    $table->string('id')->primary();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->timestamps();
  });

  // 2. عمل Mock وحقنه بالاسم الصحيح الموحد للملف بالكامل ✅
  $this->jwtServiceMock = $this->mock(JwtService::class);

  // 3. تسجيل المسار الوهمي لتمرير دورة طلب HTTP كاملة عبر الـ Kernel
  Route::get('/api/test-protected-route', function () {
    return response()->json([
      'message'         => 'Passed Middleware',
      'auth_session_id' => request()->attributes->get('auth_session_id'),
      'auth_user_id'    => request()->attributes->get('auth_user_id'),
    ]);
  })->middleware(JwtMiddleware::class);
});

// =========================================================================
// السطور 29-31: غياب الهيدر تماماً
// =========================================================================
test('returns 401 when authorization header is missing', function () {
  $this->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Unauthorized']);
});

// =========================================================================
// السطور 29-31: الهيدر موجود ولكنه لا يبدأ بـ Bearer
// =========================================================================
test('returns 401 when authorization header does not start with Bearer', function () {
  $this->withHeaders(['Authorization' => 'Basic dXNlcjpwYXNz'])
    ->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Unauthorized']);
});

// =========================================================================
// السطور 37-39: فشل فك التوكن وإرجاع null
// =========================================================================
test('returns 401 when token validation fails', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn(null);

  $this->withToken('invalid-token')->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

// =========================================================================
// السطور 42-44: التوكن لا يحتوي على sid
// =========================================================================
test('returns 401 when session id (sid) is missing from token payload', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn((object)[
    'sub' => 'user-123' // بدون حقل sid
  ]);

  $this->withToken('valid-token')->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Invalid session']);
});

// =========================================================================
// السطور 50-52: الجلسة غير موجودة في قاعدة البيانات
// =========================================================================
test('returns 401 when session is not found in database', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn((object)[
    'sub' => 'user-123',
    'sid' => 'non-existent-session-id'
  ]);

  $this->withToken('valid-token')->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Session not found']);
});

// =========================================================================
// السطور 54-56: الجلسة ملغاة revoked
// =========================================================================
test('returns 401 when session has been revoked', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn((object)[
    'sub' => 'user-123',
    'sid' => 'revoked-id'
  ]);

  DB::table('my_sessions')->insert([
    'id'         => 'revoked-id',
    'revoked_at' => now(), // ممتلئ يعني ملغاة
    'expires_at' => now()->addHour()
  ]);

  $this->withToken('valid-token')->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Session revoked']);
});

// =========================================================================
// السطور 58-60: الجلسة منتهية الصلاحية expired
// =========================================================================
test('returns 401 when session has expired', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn((object)[
    'sub' => 'user-123',
    'sid' => 'expired-id'
  ]);

  DB::table('my_sessions')->insert([
    'id'         => 'expired-id',
    'revoked_at' => null,
    'expires_at' => now()->subMinute() // منتهية قبل دقيقة
  ]);

  $this->withToken('valid-token')->getJson('/api/test-protected-route')
    ->assertStatus(401)
    ->assertJson(['message' => 'Session expired']);
});

// =========================================================================
// السطور 65-70: مسار النجاح الكامل وتخزين الـ Attributes والـ Next Closure
// =========================================================================
test('passes request and sets attributes when token and session are valid', function () {
  $this->jwtServiceMock->shouldReceive('validateToken')->andReturn((object)[
    'sub' => 'user-777',
    'sid' => 'active-session-id'
  ]);

  DB::table('my_sessions')->insert([
    'id'         => 'active-session-id',
    'revoked_at' => null,
    'expires_at' => now()->addHours(2) // صالحة
  ]);

  $this->withToken('valid-token')->getJson('/api/test-protected-route')
    ->assertStatus(200)
    ->assertJson([
      'message'         => 'Passed Middleware',
      'auth_session_id' => 'active-session-id',
      'auth_user_id'    => 'user-777'
    ]);
});
