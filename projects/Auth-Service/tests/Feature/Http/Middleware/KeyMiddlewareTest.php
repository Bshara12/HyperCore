<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\KeyMiddleware;
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
    "private_key_bits" => 2048, // تم التعديل هنا لرفع الحماية ورضا المكتبة ✅
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
  ]);

  // استخراج المفتاح الخاص لتوقيع التوكنات داخل الاختبار
  openssl_pkey_export($res, $this->privateKey);

  // استخراج المفتاح العام وحفظه في المسار الذي يتوقعه الميدل وير
  $publicKeyDetails = openssl_pkey_get_details($res);
  $this->publicKeyPath = storage_path('keys/public.key');
  File::put($this->publicKeyPath, $publicKeyDetails["key"]);

  // 3. تسجيل مسار وهمي وتطبيق الميدل وير عليه
  Route::get('/api/test-key-protected', function () {
    return response()->json([
      'message'         => 'Passed Key Middleware',
      'auth_user_id'    => request()->input('auth_user_id'),
      'auth_project_id' => request()->input('auth_project_id'),
      'auth_role'       => request()->input('auth_role'),
    ]);
  })->middleware(KeyMiddleware::class);
});

afterEach(function () {
  // تنظيف البيئة وحذف ملف المفتاح العام المؤقت بعد كل فحص
  if (File::exists($this->publicKeyPath)) {
    File::delete($this->publicKeyPath);
  }
});

// =========================================================================
// اختبار حالة: غياب التوكن (Missing Token) - الأسطر 23-25
// =========================================================================
test('returns 401 when bearer token is missing', function () {
  $this->getJson('/api/test-key-protected')
    ->assertStatus(401)
    ->assertJson(['error' => 'Missing token']);
});

// =========================================================================
// اختبار حالة: توكن غير صالح أو منتهي (Invalid Token) - الأسطر 31-35
// =========================================================================
test('returns 401 when token decoding fails or is invalid', function () {
  // تمرير توكن عشوائي غير موقع بالمفتاح الخاص الصحيح
  $this->withToken('completely-invalid-token-string')
    ->getJson('/api/test-key-protected')
    ->assertStatus(401)
    ->assertJson(['error' => 'Invalid token']);
});

// =========================================================================
// اختبار حالة: النجاح ودمج البيانات (Success & Request Merge) - الأسطر 37-43
// =========================================================================
test('passes request and merges token claims when token is perfectly valid', function () {
  // بناء الحمولة (Payload) المطلوبة للميدل وير
  $payload = [
    'sub'  => 'user_id_99',
    'proj' => 'project_id_100',
    'role' => 'admin',
    'iat'  => time(),
    'exp'  => time() + 3600
  ];

  // تشفير التوكن باستخدام المفتاح الخاص الذي ولدناه في الـ beforeEach
  $validToken = JWT::encode($payload, $this->privateKey, 'RS256');

  // إرسال الطلب والتحقق من النتيجة ودمج البيانات بنجاح
  $this->withToken($validToken)
    ->getJson('/api/test-key-protected')
    ->assertStatus(200)
    ->assertJson([
      'message'         => 'Passed Key Middleware',
      'auth_user_id'    => 'user_id_99',
      'auth_project_id' => 'project_id_100',
      'auth_role'       => 'admin',
    ]);
});
