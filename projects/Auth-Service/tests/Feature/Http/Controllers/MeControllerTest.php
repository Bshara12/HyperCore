<?php

use App\Models\User;
use App\Services\JwtService;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * دالة تهيئة لضمان تطابق المفتاحين العام والخاص في بيئة الاختبار بنسبة 100%
 */
beforeEach(function () {
  $privateKey = app(JwtService::class)->returnInfo()['private'];
  $privateKeyResource = openssl_pkey_get_private($privateKey);

  if ($privateKeyResource) {
    $keyDetails = openssl_pkey_get_details($privateKeyResource);
    $publicKey = $keyDetails['key'];

    File::ensureDirectoryExists(storage_path('keys'));
    File::put(storage_path('keys/public_key'), $publicKey);
  }
});

/*
|--------------------------------------------------------------------------
| Tests for index() - For Services (Manual JWT Decode)
|--------------------------------------------------------------------------
*/
test('index returns user data when a valid service token is provided', function () {
  $user = User::factory()->create();
  $privateKey = app(JwtService::class)->returnInfo()['private'];

  $payload = [
    'sub' => $user->id,
    'iss' => config('app.url'),
    'type' => 'service',
    'iat' => time(),
    'exp' => time() + 3600
  ];

  $token = JWT::encode($payload, $privateKey, 'RS256');

  $response = $this->withToken($token)->getJson('/api/me');

  $response->assertStatus(200);
  expect($response->json('id'))->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Tests for profile($id) - For Services (Route Param)
|--------------------------------------------------------------------------
*/
test('profile returns specific user data with roles and permessions', function () {
  $user = User::factory()->create();
  $privateKey = app(JwtService::class)->returnInfo()['private'];

  $payload = [
    'iss' => config('app.url'),
    'type' => 'service',
    'iat' => time(),
    'exp' => time() + 3600
  ];

  $token = JWT::encode($payload, $privateKey, 'RS256');

  $response = $this->withToken($token)->getJson("/api/profile/{$user->id}");

  $response->assertStatus(200)
    ->assertJsonStructure([
      'data' => [
        'id',
        'name',
        'email',
        'roles'
      ]
    ]);

  expect($response->json('data.id'))->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Tests for myProfile() - For Users (JwtService Validation)
|--------------------------------------------------------------------------
*/
test('myProfile returns current authenticated user data when token is valid', function () {
  $user = User::factory()->create();

  // 1️⃣ إنشاء UUID فريد للجلسة
  $sessionId = (string) Str::uuid();

  // 2️⃣ زرع الجلسة داخل قاعدة بيانات الاختبار المؤقتة لتخطي فحص الـ Middleware
  DB::table('my_sessions')->insert([
    'id'         => $sessionId,
    'user_id'    => $user->id, // اضبط اسم الحقل لو كان مختلفاً في جدولك (مثل user_id أو member_id)
    'revoked_at' => null,      // لضمان أنها غير ملغاة
    'expires_at' => now()->addHours(2), // لضمان أنها صلاحيتها ممتدة
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // 3️⃣ توليد التوكن وتمرير الـ sessionId الذي قمنا بزرعه للتو
  $token = app(JwtService::class)->generateToken($user, $sessionId);

  // 4️⃣ إرسال الطلب
  $response = $this->withToken($token)->getJson('/api/my-profile');

  // الفحص والتأكد من نجاح العملية
  $response->assertStatus(200)
    ->assertJsonStructure([
      'data' => [
        'id',
        'name',
        'roles'
      ]
    ]);

  expect($response->json('data.id'))->toBe($user->id);
});

test('myProfile returns 401 unauthorized when token is invalid', function () {
  $response = $this->withToken('this-is-a-completely-invalid-token')
    ->getJson('/api/my-profile');

  $response->assertStatus(401)
    ->assertJson([
      'message' => 'Invalid or expired token'
    ]);
});

test('myProfile returns 401 when token becomes invalid inside controller', function () {
  $user = User::factory()->create();
  $sessionId = (string) \Illuminate\Support\Str::uuid();

  // زرع الجلسة لتخطي الميدلوير
  \Illuminate\Support\Facades\DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $user->id,
    'revoked_at' => null,
    'expires_at' => now()->addHours(2),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // محاكاة الـ Service: المرة الأولى تنجح (للميدلوير) والمرة الثانية تعيد false (للكنترولر)
  $mockJwtService = Mockery::mock(JwtService::class);

  // فك التشفير للميدلوير (يُعيد كائن صالح)
  $mockJwtService->shouldReceive('validateToken')
    ->once()
    ->andReturn((object)['sub' => $user->id, 'sid' => $sessionId]);

  // فك التشفير للكنترولر (يُعيد false لتجربة خطأ السطر 45)
  $mockJwtService->shouldReceive('validateToken')
    ->once()
    ->andReturn(false);

  // ✅ تصحيح: إضافة علامة $ قبل this
  $this->app->instance(JwtService::class, $mockJwtService);

  // إرسال أي توكن شكلي
  $response = $this->withToken('fake-token')->getJson('/api/my-profile');

  // الفحص للتأكد من التقاط الـ Unauthorized الخاصة بالكنترولر
  $response->assertStatus(401)
    ->assertJson(['message' => 'Unauthorized']);
});
