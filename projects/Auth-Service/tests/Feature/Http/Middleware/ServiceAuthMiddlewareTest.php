<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\ServiceAuthMiddleware;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

beforeEach(function () {
  // 1. إنشاء مجلد المفاتيح داخل الـ storage إذا لم يكن موجوداً
  $keyPath = storage_path('keys');
  if (!File::exists($keyPath)) {
    File::makeDirectory($keyPath, 0755, true);
  }

  // 2. توليد زوج مفاتيح RSA بقوة 2048 بت لتلبية شروط مكتبة php-jwt
  $res = openssl_pkey_new([
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
  ]);

  // استخراج المفتاح الخاص لتوقيع التوكنات داخل الاختبار
  openssl_pkey_export($res, $this->privateKey);

  // استخراج المفتاح العام وحفظه في المسار الذي يتوقعه الميدل وير
  $publicKeyDetails = openssl_pkey_get_details($res);
  $this->publicKeyPath = storage_path('keys/public.key');
  File::put($this->publicKeyPath, $publicKeyDetails["key"]);

  // 3. تسجيل مسار وهمي وتطبيق الميدل وير عليه
  Route::get('/api/test-service-protected', function () {
    return response()->json(['message' => 'Passed Service Auth Middleware']);
  })->middleware(ServiceAuthMiddleware::class);
});

afterEach(function () {
  // تنظيف البيئة وحذف ملف المفتاح العام المؤقت بعد كل فحص
  if (File::exists($this->publicKeyPath)) {
    File::delete($this->publicKeyPath);
  }
});

// =========================================================================
// اختبار حالة: غياب التوكن (Token missing) - الأسطر 15-17
// =========================================================================
test('returns 401 when bearer token is missing', function () {
  $this->getJson('/api/test-service-protected')
    ->assertStatus(401)
    ->assertJson(['error' => 'Token missing']);
});

// =========================================================================
// اختبار حالة: توكن غير صالح (Invalid token) - الأسطر 23-27
// =========================================================================
test('returns 401 when token is invalid or corrupted', function () {
  $this->withToken('invalid-token-format-string')
    ->getJson('/api/test-service-protected')
    ->assertStatus(401)
    ->assertJson(['error' => 'Invalid token']);
});

// =========================================================================
// اختبار حالة: نوع التوكن خاطئ (Invalid token type) - الأسطر 29-31
// =========================================================================
test('returns 403 when token type is not service', function () {
  // بناء حمولة بنوع مختلف مثل 'user' أو 'platform'
  $payload = [
    'type' => 'platform', // ليس service
    'iat'  => time(),
    'exp'  => time() + 3600
  ];

  $invalidTypeToken = JWT::encode($payload, $this->privateKey, 'RS256');

  $this->withToken($invalidTypeToken)
    ->getJson('/api/test-service-protected')
    ->assertStatus(403)
    ->assertJson(['error' => 'Invalid token type']);
});

// =========================================================================
// اختبار مسار النجاح: التوكن سليم والنوع صحيح (200 OK) - سطر 33
// =========================================================================
test('passes request when token is valid and type is service', function () {
  // بناء حمولة بنوع 'service' صحيح ومتوافق مع الشرط
  $payload = [
    'type' => 'service',
    'iat'  => time(),
    'exp'  => time() + 3600
  ];

  $validToken = JWT::encode($payload, $this->privateKey, 'RS256');

  $this->withToken($validToken)
    ->getJson('/api/test-service-protected')
    ->assertStatus(200)
    ->assertJson(['message' => 'Passed Service Auth Middleware']);
});
